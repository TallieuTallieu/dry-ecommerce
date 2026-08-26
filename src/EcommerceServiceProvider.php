<?php

namespace Tnt\Ecommerce;

use Oak\Contracts\Config\RepositoryInterface;
use Oak\Contracts\Container\ContainerInterface;
use Oak\Contracts\Dispatcher\DispatcherInterface;
use Oak\Migration\MigrationManager;
use Oak\Migration\Migrator;
use Oak\ServiceProvider;
use Tnt\Ecommerce\Account\GuestUserResolver;
use Tnt\Ecommerce\Cart\Cart;
use Tnt\Ecommerce\Cart\SessionCartStorage;
use Tnt\Ecommerce\Contracts\AttributeStorageInterface;
use Tnt\Ecommerce\Contracts\CartInterface;
use Tnt\Ecommerce\Contracts\CartStorageInterface;
use Tnt\Ecommerce\Contracts\PaymentInterface;
use Tnt\Ecommerce\Contracts\ShopInterface;
use Tnt\Ecommerce\Contracts\UserResolverInterface;
use Tnt\Ecommerce\Events\Order\OrderEvent;
use Tnt\Ecommerce\Events\Order\Paid;
use Tnt\Ecommerce\Events\Order\PaymentCanceled;
use Tnt\Ecommerce\Events\Order\PaymentExpired;
use Tnt\Ecommerce\Events\Order\PaymentFailed;
use Tnt\Ecommerce\Events\Order\PaymentRefunded;
use Tnt\Ecommerce\Fulfillment\SessionAttributeStorage;
use Tnt\Ecommerce\Model\Order;
use Tnt\Ecommerce\Payment\NullPayment;
use Tnt\Ecommerce\Payment\PaymentStatus;
use Tnt\Ecommerce\Revisions\AddFulfillmentAttributesToOrderTable;
use Tnt\Ecommerce\Revisions\AddOptionsToLineTables;
use Tnt\Ecommerce\Revisions\CreateAddressTable;
use Tnt\Ecommerce\Revisions\CreateCustomerTable;
use Tnt\Ecommerce\Revisions\CreateDiscountCodeTable;
use Tnt\Ecommerce\Shop\Shop;
use Tnt\Ecommerce\Tax\PriceConvention;
use Tnt\Ecommerce\Tax\TaxPolicy;
use Tnt\Ecommerce\Revisions\CreateCartTable;
use Tnt\Ecommerce\Revisions\CreateOrderItemTable;
use Tnt\Ecommerce\Revisions\CreateOrderTable;
use Tnt\Ecommerce\Revisions\CreateFulfillmentMethodTable;
use Tnt\Ecommerce\Revisions\CreateCartItemTable;
use Tnt\Ecommerce\Revisions\CreateStockItemTable;
use Tnt\Ecommerce\Revisions\CreateStockTable;

class EcommerceServiceProvider extends ServiceProvider
{
    public function boot(ContainerInterface $app)
    {
        $this->bootEventListeners($app);

        if ($app->isRunningInConsole()) {
            // getWith() answers plain `object`; narrow before calling on it.
            /** @var Migrator $migrator */
            $migrator = $app->getWith(Migrator::class, [
                'name' => 'ecommerce',
            ]);

            $migrator->setRevisions([
                CreateCustomerTable::class,
                CreateDiscountCodeTable::class,
                CreateFulfillmentMethodTable::class,
                CreateOrderTable::class,
                CreateOrderItemTable::class,
                CreateCartTable::class,
                CreateCartItemTable::class,
                CreateStockTable::class,
                CreateStockItemTable::class,

                // Append only, never insert: Oak's migrator counts revisions
                // run rather than remembering which, so reordering makes an
                // existing shop run the wrong statement.
                CreateAddressTable::class,
                AddFulfillmentAttributesToOrderTable::class,
                AddOptionsToLineTables::class,
            ]);

            /** @var MigrationManager $manager */
            $manager = $app->get(MigrationManager::class);
            $manager->addMigrator($migrator);
        }
    }

    public function register(ContainerInterface $app)
    {
        // Singletons: a second instance of either would be a diverging copy
        // of per-request state.
        $app->singleton(
            AttributeStorageInterface::class,
            SessionAttributeStorage::class
        );
        $app->singleton(CartStorageInterface::class, SessionCartStorage::class);

        $app->singleton(ShopInterface::class, Shop::class);
        $app->singleton(CartInterface::class, Cart::class);

        // ContainerInterface::get() answers plain `object`; narrow once here.
        /** @var RepositoryInterface $config */
        $config = $app->get(RepositoryInterface::class);

        $app->singleton(
            PaymentInterface::class,
            self::configuredClass(
                $config,
                'ecommerce.payment',
                NullPayment::class
            )
        );

        // Who is signed in. The default answers "nobody" — correct for a shop
        // with no accounts.
        $app->singleton(
            UserResolverInterface::class,
            self::configuredClass(
                $config,
                'ecommerce.user_resolver',
                GuestUserResolver::class
            )
        );

        // How the shop taxes. Both defaults leave an existing shop's totals
        // where they were: inclusive prices, delivery at 0%.
        $app->singleton(TaxPolicy::class, function () use ($config): TaxPolicy {
            return new TaxPolicy(
                self::configuredConvention($config),
                self::configuredRate($config, 'ecommerce.delivery_tax_rate')
            );
        });

        // StockWorkerInterface is deliberately not bound: a worker cannot be
        // built without being told which stock it counts.
    }

    /**
     * The convention a shop quotes its prices under. Anything unset or
     * unrecognised reads as inclusive — the reading that leaves totals alone.
     *
     * @param RepositoryInterface $config
     * @return PriceConvention
     */
    private static function configuredConvention(
        RepositoryInterface $config
    ): PriceConvention {
        $configured = $config->get('ecommerce.prices');

        if (!is_string($configured)) {
            return PriceConvention::Inclusive;
        }

        return PriceConvention::tryFrom($configured) ??
            PriceConvention::Inclusive;
    }

    /**
     * A rate from configuration, or 0 when the shop has not set one. Anything
     * that is not a number reads as 0 rather than being coerced.
     *
     * @param RepositoryInterface $config
     * @param string $key
     * @return int|float
     */
    private static function configuredRate(
        RepositoryInterface $config,
        string $key
    ): int|float {
        $configured = $config->get($key);

        return is_int($configured) || is_float($configured) ? $configured : 0;
    }

    /**
     * The class a config key names, or the default when it names nothing.
     * Anything that is not a class name falls back rather than erroring while
     * booting.
     *
     * @param RepositoryInterface $config
     * @param string $key
     * @param class-string $default
     * @return string
     */
    private static function configuredClass(
        RepositoryInterface $config,
        string $key,
        string $default
    ): string {
        $configured = $config->get($key);

        return is_string($configured) && $configured !== ''
            ? $configured
            : $default;
    }

    /**
     * The payment-event listeners: each event writes its status onto the
     * order, and {@see Paid} additionally redeems the coupon. Orders that are
     * not this package's {@see Order} are left alone. See docs/payment.md.
     *
     * @param ContainerInterface $app
     * @return void
     */
    private function bootEventListeners(ContainerInterface $app): void
    {
        /** @var DispatcherInterface $dispatcher */
        $dispatcher = $app->get(DispatcherInterface::class);

        $statuses = [
            Paid::class => PaymentStatus::Paid,
            PaymentFailed::class => PaymentStatus::Failed,
            PaymentCanceled::class => PaymentStatus::Canceled,
            PaymentExpired::class => PaymentStatus::Expired,
            PaymentRefunded::class => PaymentStatus::Refunded,
        ];

        foreach ($statuses as $event => $status) {
            $dispatcher->addListener($event, function (OrderEvent $event) use (
                $status
            ): void {
                $order = $event->getOrder();

                if ($order instanceof Order) {
                    $order->setPaymentStatus($status);
                }
            });
        }

        $dispatcher->addListener(Paid::class, function (Paid $paidEvent): void {
            $order = $paidEvent->getOrder();

            if (!($order instanceof Order)) {
                return;
            }

            $discount = $order->discount;

            if ($discount === null) {
                return;
            }

            $coupon = $discount->coupon;

            if ($coupon !== null && $coupon->isRedeemable($order)) {
                $coupon->redeem($order);
            }
        });
    }
}

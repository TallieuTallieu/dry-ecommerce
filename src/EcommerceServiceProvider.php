<?php

namespace Tnt\Ecommerce;

use Oak\Contracts\Config\RepositoryInterface;
use Oak\Contracts\Console\KernelInterface;
use Oak\Contracts\Container\ContainerInterface;
use Oak\Contracts\Dispatcher\DispatcherInterface;
use Oak\Migration\MigrationManager;
use Oak\Migration\Migrator;
use Oak\ServiceProvider;
use Tnt\Ecommerce\Account\GuestUserResolver;
use Tnt\Ecommerce\Cart\Cart;
use Tnt\Ecommerce\Cart\CartLifetime;
use Tnt\Ecommerce\Cart\CartRelease;
use Tnt\Ecommerce\Cart\CookieCartStorage;
use Tnt\Ecommerce\Cart\SessionCartStorage;
use Tnt\Ecommerce\Console\ReapDraftsCommand;
use Tnt\Ecommerce\Contracts\AttributeStorageInterface;
use Tnt\Ecommerce\Contracts\CartInterface;
use Tnt\Ecommerce\Contracts\CartStorageInterface;
use Tnt\Ecommerce\Contracts\PaymentGatewayInterface;
use Tnt\Ecommerce\Contracts\PaymentInterface;
use Tnt\Ecommerce\Contracts\RedirectorInterface;
use Tnt\Ecommerce\Contracts\ShopInterface;
use Tnt\Ecommerce\Contracts\UserResolverInterface;
use Tnt\Ecommerce\Events\Order\OrderEvent;
use Tnt\Ecommerce\Events\Order\Paid;
use Tnt\Ecommerce\Events\Order\PaymentCanceled;
use Tnt\Ecommerce\Events\Order\PaymentExpired;
use Tnt\Ecommerce\Events\Order\PaymentFailed;
use Tnt\Ecommerce\Events\Order\PaymentRefunded;
use Tnt\Ecommerce\Fulfillment\CartAttributeStorage;
use Tnt\Ecommerce\Model\Order;
use Tnt\Ecommerce\Payment\HttpRedirector;
use Tnt\Ecommerce\Payment\NullPayment;
use Tnt\Ecommerce\Payment\PaymentStatus;
use Tnt\Ecommerce\Revisions\AddCartLifecycleColumns;
use Tnt\Ecommerce\Revisions\AddFulfillmentAttributesToOrderTable;
use Tnt\Ecommerce\Revisions\AddIndexesToEcommerceTables;
use Tnt\Ecommerce\Revisions\AddOptionsToLineTables;
use Tnt\Ecommerce\Revisions\AddOrderStateColumn;
use Tnt\Ecommerce\Revisions\CreateAddressTable;
use Tnt\Ecommerce\Revisions\CreateCustomerTable;
use Tnt\Ecommerce\Revisions\CreateDiscountCodeTable;
use Tnt\Ecommerce\Revisions\DropAddressNameColumns;
use Tnt\Ecommerce\Revisions\MakeCustomerUserUnique;
use Tnt\Ecommerce\Revisions\MakeOrderCustomerNullable;
use Tnt\Ecommerce\Revisions\MakeOrderPlacementColumnsNullable;
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
                DropAddressNameColumns::class,
                MakeOrderCustomerNullable::class,
                AddOrderStateColumn::class,
                AddCartLifecycleColumns::class,
                AddIndexesToEcommerceTables::class,
                MakeOrderPlacementColumnsNullable::class,
                MakeCustomerUserUnique::class,
            ]);

            /** @var MigrationManager $manager */
            $manager = $app->get(MigrationManager::class);
            $manager->addMigrator($migrator);

            // getWith()/get() answer plain `object`; narrow before calling.
            /** @var KernelInterface $kernel */
            $kernel = $app->get(KernelInterface::class);
            $kernel->registerCommand(ReapDraftsCommand::class);
        }
    }

    public function register(ContainerInterface $app)
    {
        // ContainerInterface::get() answers plain `object`; narrow once here.
        /** @var RepositoryInterface $config */
        $config = $app->get(RepositoryInterface::class);

        // Singletons: a second instance of either would be a diverging copy
        // of per-request state. The attributes live on the cart row through
        // whichever cart storage is bound below; SessionAttributeStorage
        // still exists for shops that bind it back themselves.
        $app->singleton(
            AttributeStorageInterface::class,
            CartAttributeStorage::class
        );

        // With a lifetime configured the cart lives in a cookie of its own
        // and survives the session; without one, today's session-backed
        // behaviour. Same fallback style as the tax policy below: an unset
        // or unusable value changes nothing.
        $lifetime = CartLifetime::days($config);

        if ($lifetime !== null) {
            $app->singleton(
                CartStorageInterface::class,
                CookieCartStorage::class
            );
            $app->whenAsksGive(
                CookieCartStorage::class,
                'lifetimeDays',
                $lifetime
            );
        } else {
            $app->singleton(
                CartStorageInterface::class,
                SessionCartStorage::class
            );
        }

        $app->singleton(ShopInterface::class, Shop::class);
        $app->singleton(CartInterface::class, Cart::class);

        if ($app->isRunningInConsole()) {
            $app->set(ReapDraftsCommand::class, ReapDraftsCommand::class);
        }

        $gateway = self::configuredClass(
            $config,
            'ecommerce.payment',
            NullPayment::class
        );

        $app->singleton(PaymentInterface::class, $gateway);

        // The webhook handler asks for the richer contract by name, so a
        // gateway that implements it is bound under that name too — and a
        // shop on a synchronous gateway fails to resolve PaymentWebhook
        // loudly instead of half-working.
        if (is_subclass_of($gateway, PaymentGatewayInterface::class)) {
            $app->singleton(PaymentGatewayInterface::class, $gateway);
        }

        // Where a gateway sends the visitor. A seam rather than a direct
        // call to dry's Response so pay() can run in a test without exiting.
        $app->singleton(RedirectorInterface::class, HttpRedirector::class);

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
     * order, and {@see Paid} additionally redeems the coupon and soft-deletes
     * the cart behind the order ({@see CartRelease}). Orders that are not
     * this package's {@see Order} are left alone. See docs/payment.md.
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

        // Paid also releases the basket: soft-delete, through the cart→order
        // link, so the row and its provenance survive. Resolved through the
        // container at dispatch time — the default queries ecommerce_cart,
        // and a test substitutes its own CartRelease binding.
        $dispatcher->addListener(Paid::class, function (Paid $paidEvent) use (
            $app
        ): void {
            $order = $paidEvent->getOrder();

            if (!($order instanceof Order)) {
                return;
            }

            /** @var CartRelease $release */
            $release = $app->get(CartRelease::class);
            $release->release($order);
        });
    }
}

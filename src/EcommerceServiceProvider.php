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
use Tnt\Ecommerce\Events\Order\Paid;
use Tnt\Ecommerce\Fulfillment\SessionAttributeStorage;
use Tnt\Ecommerce\Model\Order;
use Tnt\Ecommerce\Payment\NullPayment;
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

                // Appended, never inserted. Oak's migrator counts how many
                // revisions a shop has run rather than remembering which, so
                // putting a new one next to the table it relates to would
                // shift everything after it and make an existing shop run the
                // wrong statement. New revisions go on the end.
                CreateAddressTable::class,
            ]);

            $app->get(MigrationManager::class)->addMigrator($migrator);
        }
    }

    public function register(ContainerInterface $app)
    {
        // The two seams that keep the domain off the Session facade. Both are
        // singletons: a shop has one current cart and one bag of fulfillment
        // attributes per request, and a second instance of either would be a
        // second, diverging copy of that state.
        $app->singleton(
            AttributeStorageInterface::class,
            SessionAttributeStorage::class
        );
        $app->singleton(CartStorageInterface::class, SessionCartStorage::class);

        $app->singleton(ShopInterface::class, Shop::class);
        $app->singleton(CartInterface::class, Cart::class);

        // ContainerInterface::get() is typed `class-string<T>|string`, so the
        // union collapses T to plain `object` and every call on the result is
        // an unknown method. Narrowing it once here is what lets both bindings
        // below read as themselves.
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

        // Who is signed in, resolved from config exactly as the payment above
        // is. The default answers "nobody", which is the correct answer for a
        // shop with no accounts rather than a placeholder for a missing one; a
        // shop running dry-accounts points this at AccountsUserResolver and
        // needs no glue of its own.
        $app->singleton(
            UserResolverInterface::class,
            self::configuredClass(
                $config,
                'ecommerce.user_resolver',
                GuestUserResolver::class
            )
        );

        // How the shop taxes, as one value rather than two loose settings.
        // Both halves default to leaving an existing shop exactly where it
        // was: prices that already contain their tax, so getTotal() does not
        // move, and delivery untaxed, so no figure appears that the shop never
        // asked for.
        $app->singleton(TaxPolicy::class, function () use ($config): TaxPolicy {
            return new TaxPolicy(
                self::configuredConvention($config),
                self::configuredRate($config, 'ecommerce.delivery_tax_rate')
            );
        });

        // StockWorkerInterface is deliberately not bound. A worker counts one
        // named stock and cannot be built without being told which one, so the
        // binding that used to sit here could never have been resolved. A
        // buyable that has stock now hands a worker over itself, through
        // HasStockInterface.
    }

    /**
     * The class a config key names, or the default when it names nothing.
     *
     * `RepositoryInterface::get()` answers `mixed` and types its `$default`
     * parameter as `null`, so the default cannot be passed through it and the
     * value it does return has to be narrowed before the container will take
     * it. Both bindings above need the same two steps, so they happen here
     * once.
     *
     * Anything that is not a class name falls back rather than reaching the
     * container. A config key holding an array or a stray `true` is a mistake
     * in the shop's config, and the shop it breaks is better served by a
     * working default than by a container error thrown while booting.
     *
     * @param RepositoryInterface $config
     * @param string $key
     * @param class-string $default
     * @return string
     */
    /**
     * The convention a shop quotes its prices under.
     *
     * Anything unset, or set to a word this package does not know, is read as
     * inclusive. That is the reading that leaves an existing shop's totals
     * where they are, so a typo costs a wrong tax figure rather than silently
     * adding tax to every total in the shop.
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
     * A rate from configuration, or null when the shop has not set one.
     *
     * Distinguishes "no rate" from "a rate of zero" deliberately. An unset
     * delivery rate means delivery is not taxed and no figure is reported for
     * it; a rate of 0 means it is taxed, at nothing, which is what a
     * zero-rated supply is. The two print differently on an invoice.
     *
     * @param RepositoryInterface $config
     * @param string $key
     * @return int|float|null
     */
    private static function configuredRate(
        RepositoryInterface $config,
        string $key
    ): int|float|null {
        $configured = $config->get($key);

        return is_int($configured) || is_float($configured)
            ? $configured
            : null;
    }

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

    private function bootEventListeners(ContainerInterface $app)
    {
        $dispatcher = $app->get(DispatcherInterface::class);

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

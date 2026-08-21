<?php

namespace Tnt\Ecommerce;

use Oak\Contracts\Config\RepositoryInterface;
use Oak\Contracts\Container\ContainerInterface;
use Oak\Contracts\Dispatcher\DispatcherInterface;
use Oak\Migration\MigrationManager;
use Oak\Migration\Migrator;
use Oak\ServiceProvider;
use Tnt\Ecommerce\Cart\Cart;
use Tnt\Ecommerce\Cart\SessionCartStorage;
use Tnt\Ecommerce\Contracts\AttributeStorageInterface;
use Tnt\Ecommerce\Contracts\CartInterface;
use Tnt\Ecommerce\Contracts\CartStorageInterface;
use Tnt\Ecommerce\Contracts\PaymentInterface;
use Tnt\Ecommerce\Contracts\ShopInterface;
use Tnt\Ecommerce\Contracts\StockWorkerInterface;
use Tnt\Ecommerce\Events\Order\Paid;
use Tnt\Ecommerce\Fulfillment\SessionAttributeStorage;
use Tnt\Ecommerce\Model\Order;
use Tnt\Ecommerce\Payment\NullPayment;
use Tnt\Ecommerce\Revisions\CreateCustomerTable;
use Tnt\Ecommerce\Revisions\CreateDiscountCodeTable;
use Tnt\Ecommerce\Shop\Shop;
use Tnt\Ecommerce\Stock\StockWorker;
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
        $app->singleton(
            PaymentInterface::class,
            $app
                ->get(RepositoryInterface::class)
                ->get('ecommerce.payment', NullPayment::class)
        );
        $app->set(StockWorkerInterface::class, StockWorker::class);
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

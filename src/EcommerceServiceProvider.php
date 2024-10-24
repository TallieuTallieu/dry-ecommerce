<?php

namespace Tnt\Ecommerce;

use Oak\Contracts\Config\RepositoryInterface;
use Oak\Contracts\Container\ContainerInterface;
use Oak\Contracts\Dispatcher\DispatcherInterface;
use Oak\Migration\MigrationManager;
use Oak\Migration\Migrator;
use Oak\ServiceProvider;
use Tnt\Ecommerce\Cart\Cart;
use Tnt\Ecommerce\Contracts\CartInterface;
use Tnt\Ecommerce\Contracts\PaymentInterface;
use Tnt\Ecommerce\Contracts\ShopInterface;
use Tnt\Ecommerce\Contracts\StockWorkerInterface;
use Tnt\Ecommerce\Events\Order\Paid;
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

            $app->get(MigrationManager::class)
                ->addMigrator($migrator);
        }
    }

    public function register(ContainerInterface $app)
    {
        $app->singleton(ShopInterface::class, Shop::class);
        $app->singleton(CartInterface::class, Cart::class);
        $app->singleton(PaymentInterface::class, $app->get(RepositoryInterface::class)->get('ecommerce.payment', NullPayment::class));
        $app->set(StockWorkerInterface::class, StockWorker::class);
    }

    private function bootEventListeners(ContainerInterface $app)
    {
        $dispatcher = $app->get(DispatcherInterface::class);

        $dispatcher->addListener(Paid::class, function($paidEvent) {

            $order = $paidEvent->getOrder();
            $discount = $order->discount;
            $coupon = null;

            if ($discount) {
                $coupon = $discount->coupon;
            }

            if ($coupon && $coupon->isRedeemable()) {
                $coupon->redeem($order);
            }
        });
    }
}
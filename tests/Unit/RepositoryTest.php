<?php

declare(strict_types=1);

/*
 * Repositories, as far as they can be checked without a connection.
 *
 * Running a query needs a database and belongs in tests/Feature; what can be
 * pinned down here is that every repository is buildable off a booted Dry
 * application, points at the model it claims to, and composes its criteria
 * fluently — which is what the callers in src/ depend on.
 */

use Tnt\Dbi\BaseRepository;
use Tnt\Ecommerce\Model\Cart;
use Tnt\Ecommerce\Model\CartItem;
use Tnt\Ecommerce\Model\Customer;
use Tnt\Ecommerce\Model\DiscountCode;
use Tnt\Ecommerce\Model\Order;
use Tnt\Ecommerce\Model\OrderItem;
use Tnt\Ecommerce\Model\Stock;
use Tnt\Ecommerce\Model\StockItem;
use Tnt\Ecommerce\Repository\CartItemRepository;
use Tnt\Ecommerce\Repository\CartRepository;
use Tnt\Ecommerce\Repository\CustomerRepository;
use Tnt\Ecommerce\Repository\DiscountCodeRepository;
use Tnt\Ecommerce\Repository\OrderItemRepository;
use Tnt\Ecommerce\Repository\OrderRepository;
use Tnt\Ecommerce\Repository\Repository;
use Tnt\Ecommerce\Repository\StockItemRepository;
use Tnt\Ecommerce\Repository\StockRepository;

it('builds every repository without a criteria collection', function (
    string $repository
): void {
    $instance = $repository::create();
    assert($instance instanceof Repository);

    expect($instance)->toBeInstanceOf(BaseRepository::class);
})->with([
    CartRepository::class,
    CartItemRepository::class,
    CustomerRepository::class,
    DiscountCodeRepository::class,
    OrderRepository::class,
    OrderItemRepository::class,
    StockRepository::class,
    StockItemRepository::class,
]);

it('covers every model the cart and order paths read', function (
    string $repository,
    string $model
): void {
    $instance = $repository::create();
    assert($instance instanceof Repository);

    $value = (new ReflectionProperty($instance, 'model'))->getValue($instance);
    assert(is_string($value));

    expect($value)->toBe($model);
})->with([
    [CartRepository::class, Cart::class],
    [CartItemRepository::class, CartItem::class],
    [CustomerRepository::class, Customer::class],
    [DiscountCodeRepository::class, DiscountCode::class],
    [OrderRepository::class, Order::class],
    [OrderItemRepository::class, OrderItem::class],
    [StockRepository::class, Stock::class],
    [StockItemRepository::class, StockItem::class],
]);

it('composes filters fluently', function (): void {
    $repository = OrderRepository::create();

    expect(
        $repository
            ->byOrderId('12-ABCDE_FGH')
            ->withPaymentStatus('paid')
            ->orderBy('created', 'DESC')
            ->amount(10)
    )->toBe($repository);
});

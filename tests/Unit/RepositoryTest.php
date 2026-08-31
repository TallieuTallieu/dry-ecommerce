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
use Tnt\Ecommerce\Model\Address;
use Tnt\Ecommerce\Model\Cart;
use Tnt\Ecommerce\Model\CartItem;
use Tnt\Ecommerce\Model\Customer;
use Tnt\Ecommerce\Model\DiscountCode;
use Tnt\Ecommerce\Model\Order;
use Tnt\Ecommerce\Model\OrderItem;
use Tnt\Ecommerce\Model\Stock;
use Tnt\Ecommerce\Model\StockItem;
use Tnt\Ecommerce\Repository\AddressRepository;
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
    AddressRepository::class,
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
    [AddressRepository::class, Address::class],
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

it('composes the order state scopes fluently', function (): void {
    // placed() for every list a shop shows — spelled "state != draft" so
    // legacy rows, whose column holds '', stay in — drafts() for the reaper,
    // updatedBefore() for its abandonment cutoff.
    $repository = OrderRepository::create();

    expect($repository->placed()->updatedBefore(time()))->toBe($repository);

    $repository = OrderRepository::create();

    expect($repository->drafts()->updatedBefore(time()))->toBe($repository);
});

it('composes the cart afterlife lookups fluently', function (): void {
    // byToken() is the cookie storage's lookup, byOrder() the Paid
    // listener's, and notDeleted() scopes both: a soft-deleted cart is
    // absent everywhere it could read as a visitor's cart.
    $repository = CartRepository::create();

    expect($repository->byToken(str_repeat('ab', 16))->notDeleted())->toBe(
        $repository
    );

    $repository = CartRepository::create();

    expect($repository->byOrder(12)->notDeleted())->toBe($repository);
});

it('composes the cart line lookups fluently', function (): void {
    // Both spellings of "find the line": the full merge key with options —
    // which takes the IS NULL branch when there are none and the equality
    // branch when there are — and the variant-blind lookup quantityOf() and
    // remove() count and delete through. Running them needs a database;
    // that they compose off an unbooted container does not.
    $cart = new Cart();
    $buyable = new Tests\Support\FakeBuyable('1', 1000);

    $repository = CartItemRepository::create();
    expect($repository->forBuyable($cart, $buyable))->toBe($repository);

    $repository = CartItemRepository::create();
    expect(
        $repository->forBuyable($cart, $buyable, ['cheese' => 'no goat'])
    )->toBe($repository);

    $repository = CartItemRepository::create();
    expect($repository->forAnyVariantOf($cart, $buyable))->toBe($repository);
});

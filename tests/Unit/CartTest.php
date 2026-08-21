<?php

declare(strict_types=1);

/*
 * The cart's arithmetic, exercised with no session and no database.
 *
 * That is the point of these tests rather than a side effect of them: each one
 * builds a real Tnt\Ecommerce\Cart\Cart, hands it an InMemoryCartStorage and
 * asks it real questions. Nothing here is mocked with a test-double framework
 * and nothing here touches dry\db\Connection, which cannot even be autoloaded
 * outside a booted Dry application (see tests/Feature/AutoloadTest.php).
 *
 * Money is still float in this ticket — integer cents is a separate one — so
 * the amounts below are all quarters, which are exact in binary. Assertions can
 * then be strict without asserting on float noise.
 */

use Tests\Support\FakeBuyable;
use Tests\Support\FakeCoupon;
use Tests\Support\FakeDiscountCode;
use Tests\Support\FakeFulfillment;
use Tests\Support\FakePayment;
use Tnt\Ecommerce\Cart\Cart;
use Tnt\Ecommerce\Cart\InMemoryCartStorage;
use Tnt\Ecommerce\Contracts\FulfillmentInterface;
use Tnt\Ecommerce\Fulfillment\InMemoryAttributeStorage;
use Tnt\Ecommerce\Shop\Shop;

/**
 * A cart, the storage behind it and the shop it belongs to.
 *
 * @param array<int, FulfillmentInterface> $fulfillments
 * @return array{0: Cart, 1: InMemoryCartStorage, 2: Shop}
 */
function makeCart(array $fulfillments = []): array
{
    $shop = new Shop(new InMemoryAttributeStorage());

    foreach ($fulfillments as $fulfillment) {
        $shop->addFulfillment($fulfillment);
    }

    $storage = new InMemoryCartStorage();
    $cart = new Cart($shop, $storage, new FakePayment());

    return [$cart, $storage, $shop];
}

it('constructs without a session or a database', function (): void {
    [$cart] = makeCart();

    expect($cart->items())->toBe([]);
    expect($cart->getSubTotal())->toBe(0.0);
    expect($cart->getTotal())->toBe(0.0);
    expect($cart->getReduction())->toBe(0.0);
    expect($cart->getDiscount())->toBeNull();
    expect($cart->getFulfillment())->toBeNull();
    expect($cart->getFulfillmentCost())->toBe(0.0);
});

it('sums the line totals into the subtotal', function (): void {
    [$cart] = makeCart();

    $cart->add(new FakeBuyable('1', 12.5), 2);
    $cart->add(new FakeBuyable('2', 5.0), 3);

    // 2 x 12.50 + 3 x 5.00
    expect($cart->getSubTotal())->toBe(40.0);
    expect($cart->items())->toHaveCount(2);
});

it('merges a repeated buyable into one line', function (): void {
    [$cart] = makeCart();

    $cart->add(new FakeBuyable('1', 10.0), 1);
    $cart->add(new FakeBuyable('1', 10.0), 2);

    expect($cart->items())->toHaveCount(1);
    expect($cart->items()[0]->getQuantity())->toBe(3);
    expect($cart->getSubTotal())->toBe(30.0);
});

it('keeps different buyables on separate lines', function (): void {
    [$cart] = makeCart();

    $cart->add(new FakeBuyable('1', 10.0));
    $cart->add(new FakeBuyable('2', 10.0));

    expect($cart->items())->toHaveCount(2);
});

it('removes a buyable whatever its quantity', function (): void {
    [$cart] = makeCart();

    $buyable = new FakeBuyable('1', 10.0);
    $cart->add($buyable, 5);
    $cart->add(new FakeBuyable('2', 3.0));

    $cart->remove($buyable);

    expect($cart->items())->toHaveCount(1);
    expect($cart->getSubTotal())->toBe(3.0);
});

it('ignores removing something that is not in the cart', function (): void {
    [$cart] = makeCart();

    $cart->add(new FakeBuyable('1', 10.0));
    $cart->remove(new FakeBuyable('2', 10.0));

    expect($cart->items())->toHaveCount(1);
});

it('empties on clear', function (): void {
    [$cart] = makeCart();

    $cart->add(new FakeBuyable('1', 10.0), 4);
    $cart->clear();

    expect($cart->items())->toBe([]);
    expect($cart->getSubTotal())->toBe(0.0);
});

it('adds the fulfillment cost to the total', function (): void {
    $fulfillment = new FakeFulfillment('post', 4.75);
    [$cart] = makeCart([$fulfillment]);

    $cart->add(new FakeBuyable('1', 20.0), 2);
    $cart->setFulfillment($fulfillment);

    expect($cart->getFulfillment())->toBe($fulfillment);
    expect($cart->getFulfillmentCost())->toBe(4.75);
    expect($cart->getSubTotal())->toBe(40.0);
    expect($cart->getTotal())->toBe(44.75);
});

it('refuses a fulfillment method the shop does not offer', function (): void {
    [$cart, $storage] = makeCart();

    $cart->setFulfillment(new FakeFulfillment('post', 4.75));

    expect($storage->getFulfillmentId())->toBeNull();
    expect($cart->getFulfillment())->toBeNull();
    expect($cart->getFulfillmentCost())->toBe(0.0);
});

it('ignores a stored id the shop no longer offers', function (): void {
    [$cart, $storage] = makeCart();

    // A method that was on offer when the visitor chose it, and has since been
    // withdrawn.
    $storage->setFulfillmentId('post');

    expect($cart->getFulfillment())->toBeNull();
    expect($cart->getFulfillmentCost())->toBe(0.0);
});

it('takes the coupon reduction off the total', function (): void {
    $fulfillment = new FakeFulfillment('post', 5.0);
    [$cart] = makeCart([$fulfillment]);

    $cart->add(new FakeBuyable('1', 30.0), 2);
    $cart->setFulfillment($fulfillment);
    $cart->addDiscount(FakeDiscountCode::withCoupon(new FakeCoupon(12.5)));

    expect($cart->getSubTotal())->toBe(60.0);
    expect($cart->getFulfillmentCost())->toBe(5.0);
    expect($cart->getReduction())->toBe(12.5);
    // 60.00 + 5.00 - 12.50
    expect($cart->getTotal())->toBe(52.5);
});

it('leaves the subtotal alone when a coupon applies', function (): void {
    [$cart] = makeCart();

    $cart->add(new FakeBuyable('1', 40.0));
    $cart->addDiscount(FakeDiscountCode::withCoupon(new FakeCoupon(10.0)));

    expect($cart->getSubTotal())->toBe(40.0);
    expect($cart->getTotal())->toBe(30.0);
});

it('refuses a code whose coupon is not redeemable', function (): void {
    [$cart, $storage] = makeCart();

    $cart->add(new FakeBuyable('1', 40.0));
    $cart->addDiscount(
        FakeDiscountCode::withCoupon(new FakeCoupon(10.0, redeemable: false))
    );

    expect($storage->getDiscount())->toBeNull();
    expect($cart->getDiscount())->toBeNull();
    expect($cart->getReduction())->toBe(0.0);
    expect($cart->getTotal())->toBe(40.0);
});

it('refuses a code with no coupon behind it', function (): void {
    [$cart, $storage] = makeCart();

    $cart->addDiscount(FakeDiscountCode::withoutCoupon());

    expect($storage->getDiscount())->toBeNull();
    expect($cart->getDiscount())->toBeNull();
});

it('re-checks an applied coupon rather than trusting it', function (): void {
    [$cart] = makeCart();
    $coupon = new FakeCoupon(10.0);

    $cart->add(new FakeBuyable('1', 40.0));
    $cart->addDiscount(FakeDiscountCode::withCoupon($coupon));

    expect($cart->getReduction())->toBe(10.0);
    expect($cart->getDiscount())->not->toBeNull();

    // The coupon runs out while the cart is still open.
    $coupon->stopBeingRedeemable();

    expect($cart->getReduction())->toBe(0.0);
    expect($cart->getDiscount())->toBeNull();
    expect($cart->getTotal())->toBe(40.0);
});

it('drops the discount when the cart is cleared', function (): void {
    [$cart] = makeCart();

    $cart->add(new FakeBuyable('1', 40.0));
    $cart->addDiscount(FakeDiscountCode::withCoupon(new FakeCoupon(10.0)));
    $cart->clear();

    expect($cart->getDiscount())->toBeNull();
    expect($cart->getReduction())->toBe(0.0);
    expect($cart->getTotal())->toBe(0.0);
});

it('prices a line at quantity times unit price', function (): void {
    [$cart] = makeCart();

    $cart->add(new FakeBuyable('1', 3.25), 3);

    $item = $cart->items()[0];

    expect($item->getQuantity())->toBe(3);
    expect($item->getPrice())->toBe(9.75);
    expect($item->getTitle())->toBe('A thing');
    expect($item->getBuyable()->getId())->toBe('1');
});

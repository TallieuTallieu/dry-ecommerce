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
 * Every amount below is integer cents: 1250 is €12.50. The float quarters these
 * tests used to be written in were there so that float assertions stayed
 * deterministic; with integers that crutch is gone and the assertions are the
 * plain arithmetic they always wanted to be. The rounding rule itself lives in
 * Tnt\Ecommerce\Money and is pinned down in MoneyTest.
 */

use Tests\Support\FakeBuyable;
use Tests\Support\FakeCoupon;
use Tests\Support\FakeDiscountCode;
use Tests\Support\FakeFulfillment;
use Tests\Support\FakePayment;
use Tests\Support\PercentageCoupon;
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
    expect($cart->getSubTotal())->toBe(0);
    expect($cart->getTotal())->toBe(0);
    expect($cart->getReduction())->toBe(0);
    expect($cart->getDiscount())->toBeNull();
    expect($cart->getFulfillment())->toBeNull();
    expect($cart->getFulfillmentCost())->toBe(0);
});

it('sums the line totals into the subtotal', function (): void {
    [$cart] = makeCart();

    $cart->add(new FakeBuyable('1', 1250), 2);
    $cart->add(new FakeBuyable('2', 500), 3);

    // 2 x €12.50 + 3 x €5.00
    expect($cart->getSubTotal())->toBe(4000);
    expect($cart->items())->toHaveCount(2);
});

it('accumulates amounts a float would drift on', function (): void {
    [$cart] = makeCart();

    // 0.1 + 0.2 + 0.3 is 0.6000000000000001 in float, so this exact cart is
    // what the old float subtotal got wrong. In cents there is nothing to get
    // wrong.
    $cart->add(new FakeBuyable('1', 10));
    $cart->add(new FakeBuyable('2', 20));
    $cart->add(new FakeBuyable('3', 30));

    expect($cart->getSubTotal())->toBe(60);
});

it('stays exact over many small lines', function (): void {
    [$cart] = makeCart();

    // A hundred lines of €0.07. Summing 0.07 a hundred times in float gives
    // 7.000000000000005.
    for ($i = 1; $i <= 100; $i++) {
        $cart->add(new FakeBuyable((string) $i, 7));
    }

    expect($cart->getSubTotal())->toBe(700);
});

it('merges a repeated buyable into one line', function (): void {
    [$cart] = makeCart();

    $cart->add(new FakeBuyable('1', 1000), 1);
    $cart->add(new FakeBuyable('1', 1000), 2);

    expect($cart->items())->toHaveCount(1);
    expect($cart->items()[0]->getQuantity())->toBe(3);
    expect($cart->getSubTotal())->toBe(3000);
});

it('keeps different buyables on separate lines', function (): void {
    [$cart] = makeCart();

    $cart->add(new FakeBuyable('1', 1000));
    $cart->add(new FakeBuyable('2', 1000));

    expect($cart->items())->toHaveCount(2);
});

it('removes a buyable whatever its quantity', function (): void {
    [$cart] = makeCart();

    $buyable = new FakeBuyable('1', 1000);
    $cart->add($buyable, 5);
    $cart->add(new FakeBuyable('2', 300));

    $cart->remove($buyable);

    expect($cart->items())->toHaveCount(1);
    expect($cart->getSubTotal())->toBe(300);
});

it('ignores removing something that is not in the cart', function (): void {
    [$cart] = makeCart();

    $cart->add(new FakeBuyable('1', 1000));
    $cart->remove(new FakeBuyable('2', 1000));

    expect($cart->items())->toHaveCount(1);
});

it('empties on clear', function (): void {
    [$cart] = makeCart();

    $cart->add(new FakeBuyable('1', 1000), 4);
    $cart->clear();

    expect($cart->items())->toBe([]);
    expect($cart->getSubTotal())->toBe(0);
});

it('adds the fulfillment cost to the total', function (): void {
    $fulfillment = new FakeFulfillment('post', 475);
    [$cart] = makeCart([$fulfillment]);

    $cart->add(new FakeBuyable('1', 2000), 2);
    $cart->setFulfillment($fulfillment);

    expect($cart->getFulfillment())->toBe($fulfillment);
    expect($cart->getFulfillmentCost())->toBe(475);
    expect($cart->getSubTotal())->toBe(4000);
    expect($cart->getTotal())->toBe(4475);
});

it('refuses a fulfillment method the shop does not offer', function (): void {
    [$cart, $storage] = makeCart();

    $cart->setFulfillment(new FakeFulfillment('post', 475));

    expect($storage->getFulfillmentId())->toBeNull();
    expect($cart->getFulfillment())->toBeNull();
    expect($cart->getFulfillmentCost())->toBe(0);
});

it('ignores a stored id the shop no longer offers', function (): void {
    [$cart, $storage] = makeCart();

    // A method that was on offer when the visitor chose it, and has since been
    // withdrawn.
    $storage->setFulfillmentId('post');

    expect($cart->getFulfillment())->toBeNull();
    expect($cart->getFulfillmentCost())->toBe(0);
});

it('takes the coupon reduction off the total', function (): void {
    $fulfillment = new FakeFulfillment('post', 500);
    [$cart] = makeCart([$fulfillment]);

    $cart->add(new FakeBuyable('1', 3000), 2);
    $cart->setFulfillment($fulfillment);
    $cart->addDiscount(FakeDiscountCode::withCoupon(new FakeCoupon(1250)));

    expect($cart->getSubTotal())->toBe(6000);
    expect($cart->getFulfillmentCost())->toBe(500);
    expect($cart->getReduction())->toBe(1250);
    // €60.00 + €5.00 - €12.50
    expect($cart->getTotal())->toBe(5250);
});

it(
    'rounds a percentage discount that does not divide evenly',
    function (): void {
        [$cart] = makeCart();

        // €49.99. 10% of 4999 cents is 499.9, which rounds to 500.
        $cart->add(new FakeBuyable('1', 4999));
        $cart->addDiscount(
            FakeDiscountCode::withCoupon(new PercentageCoupon(10))
        );

        expect($cart->getSubTotal())->toBe(4999);
        expect($cart->getReduction())->toBe(500);
        expect($cart->getTotal())->toBe(4499);
    }
);

it('rounds a percentage discount landing on a half cent up', function (): void {
    [$cart] = makeCart();

    // €0.25 at 6%: exactly 1.5 cents off, which rounds away from zero to 2.
    $cart->add(new FakeBuyable('1', 25));
    $cart->addDiscount(FakeDiscountCode::withCoupon(new PercentageCoupon(6)));

    expect($cart->getReduction())->toBe(2);
    expect($cart->getTotal())->toBe(23);
});

it('rounds a percentage discount once, on the subtotal', function (): void {
    $fulfillment = new FakeFulfillment('post', 495);
    [$cart] = makeCart([$fulfillment]);

    // Two lines of €12.50, so a subtotal of 2500 and a 21% reduction of exactly
    // 525 — not the 526 that rounding the two lines separately would give. A
    // cart-level percentage applies to the cart, so it is rounded there, once,
    // and the fulfillment cost is added afterwards rather than discounted.
    $cart->add(new FakeBuyable('1', 1250));
    $cart->add(new FakeBuyable('2', 1250));
    $cart->setFulfillment($fulfillment);
    $cart->addDiscount(FakeDiscountCode::withCoupon(new PercentageCoupon(21)));

    expect($cart->getSubTotal())->toBe(2500);
    expect($cart->getReduction())->toBe(525);
    expect($cart->getTotal())->toBe(2500 + 495 - 525);
});

it('leaves the subtotal alone when a coupon applies', function (): void {
    [$cart] = makeCart();

    $cart->add(new FakeBuyable('1', 4000));
    $cart->addDiscount(FakeDiscountCode::withCoupon(new FakeCoupon(1000)));

    expect($cart->getSubTotal())->toBe(4000);
    expect($cart->getTotal())->toBe(3000);
});

it('refuses a code whose coupon is not redeemable', function (): void {
    [$cart, $storage] = makeCart();

    $cart->add(new FakeBuyable('1', 4000));
    $cart->addDiscount(
        FakeDiscountCode::withCoupon(new FakeCoupon(1000, redeemable: false))
    );

    expect($storage->getDiscount())->toBeNull();
    expect($cart->getDiscount())->toBeNull();
    expect($cart->getReduction())->toBe(0);
    expect($cart->getTotal())->toBe(4000);
});

it('refuses a code with no coupon behind it', function (): void {
    [$cart, $storage] = makeCart();

    $cart->addDiscount(FakeDiscountCode::withoutCoupon());

    expect($storage->getDiscount())->toBeNull();
    expect($cart->getDiscount())->toBeNull();
});

it('re-checks an applied coupon rather than trusting it', function (): void {
    [$cart] = makeCart();
    $coupon = new FakeCoupon(1000);

    $cart->add(new FakeBuyable('1', 4000));
    $cart->addDiscount(FakeDiscountCode::withCoupon($coupon));

    expect($cart->getReduction())->toBe(1000);
    expect($cart->getDiscount())->not->toBeNull();

    // The coupon runs out while the cart is still open.
    $coupon->stopBeingRedeemable();

    expect($cart->getReduction())->toBe(0);
    expect($cart->getDiscount())->toBeNull();
    expect($cart->getTotal())->toBe(4000);
});

it('drops the discount when the cart is cleared', function (): void {
    [$cart] = makeCart();

    $cart->add(new FakeBuyable('1', 4000));
    $cart->addDiscount(FakeDiscountCode::withCoupon(new FakeCoupon(1000)));
    $cart->clear();

    expect($cart->getDiscount())->toBeNull();
    expect($cart->getReduction())->toBe(0);
    expect($cart->getTotal())->toBe(0);
});

it('prices a line at quantity times unit price', function (): void {
    [$cart] = makeCart();

    $cart->add(new FakeBuyable('1', 325), 3);

    $item = $cart->items()[0];

    expect($item->getQuantity())->toBe(3);
    expect($item->getPrice())->toBe(975);
    expect($item->getTitle())->toBe('A thing');
    expect($item->getBuyable()->getId())->toBe('1');
});

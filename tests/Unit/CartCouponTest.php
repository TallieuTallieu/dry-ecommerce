<?php

declare(strict_types=1);

/*
 * The cart holds every coupon to what it may reach: the lines that take a
 * discount, plus delivery for a coupon that reaches it — the package's own
 * coupons and a shop's alike. And it taxes delivery on what is left of it.
 */

use Tests\Support\FakeBuyable;
use Tests\Support\FakeCoupon;
use Tests\Support\FakeDiscountCode;
use Tests\Support\FakeFulfillment;
use Tests\Support\FakeTaxableBuyable;
use Tests\Support\FakeUndiscountedBuyable;
use Tests\Support\InMemoryCoupon;
use Tests\Support\PercentageTaxRate;
use Tnt\Ecommerce\Money;
use Tnt\Ecommerce\Tax\PriceConvention;
use Tnt\Ecommerce\Tax\TaxPolicy;

it(
    'holds a shop\'s own coupon to the lines, never delivery',
    function (): void {
        $fulfillment = new FakeFulfillment('post', 500);
        [$cart] = makeCart([$fulfillment]);

        $cart->add(new FakeBuyable('1', 4000));
        $cart->setFulfillment($fulfillment);
        $cart->addDiscount(FakeDiscountCode::withCoupon(new FakeCoupon(10000)));

        expect($cart->getReduction())->toBe(4000);
        expect($cart->getTotal())->toBe(500);
    }
);

it('holds any coupon off a line that takes no discount', function (): void {
    [$cart] = makeCart();

    $cart->add(new FakeBuyable('1', 3000));
    $cart->add(new FakeUndiscountedBuyable('2', 5000));
    $cart->addDiscount(FakeDiscountCode::withCoupon(new FakeCoupon(6000)));

    expect($cart->getReduction())->toBe(3000);
    expect($cart->getTotal())->toBe(5000);
});

it('lets a fixed coupon reach delivery, up to its balance', function (): void {
    $fulfillment = new FakeFulfillment('post', 500);
    [$cart] = makeCart([$fulfillment]);

    $cart->add(new FakeBuyable('1', 3000));
    $cart->add(new FakeUndiscountedBuyable('2', 1000));
    $cart->setFulfillment($fulfillment);
    $cart->addDiscount(
        FakeDiscountCode::withCoupon(InMemoryCoupon::fixed(10000))
    );

    // 3000 + 500 reachable; the deposit is paid in full.
    expect($cart->getReduction())->toBe(3500);
    expect($cart->getTotal())->toBe(1000);
});

it('keeps a percentage coupon off delivery', function (): void {
    $fulfillment = new FakeFulfillment('post', 500);
    [$cart] = makeCart([$fulfillment]);

    $cart->add(new FakeBuyable('1', 3000));
    $cart->setFulfillment($fulfillment);
    $cart->addDiscount(
        FakeDiscountCode::withCoupon(InMemoryCoupon::percentage(100))
    );

    expect($cart->getReduction())->toBe(3000);
    expect($cart->getTotal())->toBe(500);
});

it('taxes delivery on what is left of it after its share', function (): void {
    $fulfillment = new FakeFulfillment('post', 1000);
    $policy = new TaxPolicy(PriceConvention::Inclusive, 21);
    [$cart] = makeCart([$fulfillment], null, $policy);

    $rate = new PercentageTaxRate(6);
    $cart->add(new FakeTaxableBuyable('1', 3000, $rate));
    $cart->setFulfillment($fulfillment);
    $cart->addDiscount(
        FakeDiscountCode::withCoupon(InMemoryCoupon::fixed(2000))
    );

    // 2000 over [3000, 1000]: 1500 off the line, 500 off delivery.
    expect($cart->getTax())->toBe(
        $policy->taxOn(1500, $rate) + $policy->taxOnDelivery(500)
    );
});

it('taxes a line that takes no discount on its whole price', function (): void {
    $policy = new TaxPolicy(PriceConvention::Inclusive, 0);
    [$cart] = makeCart([], null, $policy);

    $rate = new PercentageTaxRate(21);
    $cart->add(new FakeTaxableBuyable('1', 2000, $rate));
    $cart->add(new FakeUndiscountedBuyable('2', 1000, $rate));
    $cart->addDiscount(FakeDiscountCode::withCoupon(new FakeCoupon(500)));

    expect($cart->getTax())->toBe(
        $policy->taxOn(1500, $rate) + $policy->taxOn(1000, $rate)
    );
});

it(
    'taxes exactly as before when nothing opts out and delivery is out of reach',
    function (): void {
        // The arithmetic getTax() had before coupons could reach delivery: one
        // apportion over every line's total, each line taxed on the rest, and
        // delivery taxed whole.
        $fulfillment = new FakeFulfillment('post', 495);
        $policy = new TaxPolicy(PriceConvention::Exclusive, 21);
        [$cart] = makeCart([$fulfillment], null, $policy);

        $high = new PercentageTaxRate(21);
        $low = new PercentageTaxRate(6);
        $cart->add(new FakeTaxableBuyable('1', 1999, $high), 3);
        $cart->add(new FakeTaxableBuyable('2', 749, $low));
        $cart->add(new FakeBuyable('3', 333));
        $cart->setFulfillment($fulfillment);
        $cart->addDiscount(FakeDiscountCode::withCoupon(new FakeCoupon(997)));

        $shares = Money::apportion(997, [5997, 749, 333]);
        $expected =
            $policy->taxOn(5997 - $shares[0], $high) +
            $policy->taxOn(749 - $shares[1], $low) +
            $policy->taxOnDelivery(495);

        expect($cart->getReduction())->toBe(997);
        expect($cart->getTax())->toBe($expected);
    }
);

it(
    'reads no line for a reduction when no coupon is in force',
    function (): void {
        $policy = new TaxPolicy(PriceConvention::Exclusive, 21);
        [$cart] = makeCart([], null, $policy);

        $rate = new PercentageTaxRate(21);
        $deposit = new FakeUndiscountedBuyable('1', 1000, $rate);
        $cart->add($deposit);

        expect($cart->getTax())->toBe($policy->taxOn(1000, $rate));
        expect($cart->getTotal())->toBe(1000 + $policy->taxOn(1000, $rate));
        expect($deposit->asked)->toBe(0);
    }
);

it('takes the applied code off again', function (): void {
    [$cart, $storage] = makeCart();

    $cart->add(new FakeBuyable('1', 3000));
    $cart->addDiscount(FakeDiscountCode::withCoupon(new FakeCoupon(500)));
    $cart->removeDiscount();

    expect($storage->getDiscount())->toBeNull();
    expect($cart->getReduction())->toBe(0);
});

<?php

declare(strict_types=1);

/*
 * A line may answer per coupon (DiscountableByCouponInterface): here a deposit
 * a fixed coupon pays and a percentage never does. The coupon asking decides
 * its reduction, its minimum and the cart's split; where no coupon asks,
 * takesDiscount() still answers.
 */

use Tests\Support\FakeBuyable;
use Tests\Support\FakeDiscountCode;
use Tests\Support\FakeVoucherDepositBuyable;
use Tests\Support\InMemoryCoupon;
use Tnt\Ecommerce\Discount\CouponRefusal;
use Tnt\Ecommerce\Discount\Reach;

it('lets the coupon that asks decide the reach', function (): void {
    [$cart] = makeCart();
    $cart->add(new FakeBuyable('1', 3000));
    $cart->add(new FakeVoucherDepositBuyable('2', 1000));

    expect(Reach::of($cart)->lines())->toBe(3000);
    expect(Reach::of($cart, InMemoryCoupon::fixed(100))->lines())->toBe(4000);
    expect(Reach::of($cart, InMemoryCoupon::percentage(10))->lines())->toBe(
        3000
    );
});

it('lets a fixed coupon pay the deposit', function (): void {
    [$cart] = makeCart();
    $cart->add(new FakeBuyable('1', 3000));
    $cart->add(new FakeVoucherDepositBuyable('2', 1000));
    $cart->addDiscount(
        FakeDiscountCode::withCoupon(InMemoryCoupon::fixed(10000))
    );

    expect($cart->getReduction())->toBe(4000);
    expect($cart->getTotal())->toBe(0);
});

it('keeps a percentage off the deposit', function (): void {
    [$cart] = makeCart();
    $cart->add(new FakeBuyable('1', 3000));
    $cart->add(new FakeVoucherDepositBuyable('2', 1000));
    $cart->addDiscount(
        FakeDiscountCode::withCoupon(InMemoryCoupon::percentage(10))
    );

    expect($cart->getReduction())->toBe(300);
    expect($cart->getTotal())->toBe(3700);
});

it(
    'spreads a fixed coupon over the deposit and taxes what is left',
    function (): void {
        [$cart] = makeCart();
        $cart->add(new FakeBuyable('1', 3000));
        $cart->add(new FakeVoucherDepositBuyable('2', 1000));
        $without = $cart->getTax();

        $cart->addDiscount(
            FakeDiscountCode::withCoupon(InMemoryCoupon::fixed(2000))
        );

        // 2000 over [3000, 1000]: the deposit takes 500, and is taxed on 500.
        expect($cart->getReduction())->toBe(2000);
        expect($cart->getTax())->toBeLessThan($without);
    }
);

it(
    'counts the deposit toward a fixed coupon\'s minimum only',
    function (): void {
        [$cart] = makeCart();
        $cart->add(new FakeBuyable('1', 3000));
        $cart->add(new FakeVoucherDepositBuyable('2', 1000));

        $fixed = InMemoryCoupon::fixed(500);
        $fixed->minimum = 3500;
        $percentage = InMemoryCoupon::percentage(10);
        $percentage->minimum = 3500;

        expect($fixed->refusal($cart))->toBeNull();
        expect($percentage->refusal($cart))->toBe(CouponRefusal::BelowMinimum);
    }
);

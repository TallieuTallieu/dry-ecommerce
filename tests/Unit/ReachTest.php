<?php

declare(strict_types=1);

/*
 * What a reduction reaches, and how it spreads over it.
 *
 * Reach is the one place the cart's tax, the clamp on every coupon and a
 * project's own breakdown of a frozen order get their arithmetic from, so the
 * properties that matter are pinned here on plain amounts: shares add up,
 * an opted-out line takes none, delivery joins the one apportion only when it
 * is reached — and with nothing opted out and delivery out of reach, the
 * split is exactly the one the cart always made.
 */

use Tests\Support\CountingFulfillment;
use Tests\Support\FakeBuyable;
use Tests\Support\FakeCoupon;
use Tests\Support\FakeDeliveryCoupon;
use Tests\Support\FakeUndiscountedBuyable;
use Tests\Support\InMemoryOrder;
use Tnt\Ecommerce\Contracts\CouponInterface;
use Tnt\Ecommerce\Discount\Reach;
use Tnt\Ecommerce\Model\Coupon;
use Tnt\Ecommerce\Money;

it('splits exactly as the cart always did when nothing opts out', function (
    int $reduction,
    array $prices
): void {
    /** @var array<int, int> $prices */
    $reach = Reach::fromAmounts(
        array_map(
            static fn(int $price): array => [
                'price' => $price,
                'takesDiscount' => true,
            ],
            $prices
        ),
        475
    );

    expect($reach->split($reduction, false))->toBe([
        'lines' => Money::apportion($reduction, $prices),
        'delivery' => 0,
    ]);
})->with([
    'two rates' => [250, [2000, 500]],
    'three equal' => [100, [333, 333, 333]],
    'ties' => [1, [500, 500]],
    'odd cents' => [997, [1999, 1, 4999, 250]],
    'everything' => [3000, [1000, 2000]],
]);

it(
    'gives an opted-out line no share and leaves it out of the total',
    function (): void {
        $reach = Reach::fromAmounts([
            ['price' => 3000, 'takesDiscount' => true],
            ['price' => 5000, 'takesDiscount' => false],
            ['price' => 1000, 'takesDiscount' => true],
        ]);

        expect($reach->lines())->toBe(4000);
        expect($reach->split(400, false))->toBe([
            'lines' => [0 => 300, 1 => 0, 2 => 100],
            'delivery' => 0,
        ]);
    }
);

it(
    'spreads over lines and delivery in one apportion when delivery is reached',
    function (): void {
        $reach = Reach::fromAmounts(
            [
                ['price' => 600, 'takesDiscount' => true],
                ['price' => 900, 'takesDiscount' => false],
            ],
            500
        );

        // 1000 over [600, 0, 500]: 545.45 and 454.54 floor to 545 and 454; the
        // last cent goes to the larger remainder, delivery's.
        expect($reach->limit(true))->toBe(1100);
        expect($reach->split(1000, true))->toBe([
            'lines' => [0 => 545, 1 => 0],
            'delivery' => 455,
        ]);
    }
);

it(
    'clamps a reduction to what it may reach before splitting',
    function (): void {
        $reach = Reach::fromAmounts(
            [
                ['price' => 3000, 'takesDiscount' => true],
                ['price' => 800, 'takesDiscount' => false],
            ],
            500
        );

        expect($reach->split(99999, false))->toBe([
            'lines' => [0 => 3000, 1 => 0],
            'delivery' => 0,
        ]);
        expect($reach->split(99999, true))->toBe([
            'lines' => [0 => 3000, 1 => 0],
            'delivery' => 500,
        ]);
        expect($reach->split(-50, true))->toBe([
            'lines' => [0 => 0, 1 => 0],
            'delivery' => 0,
        ]);
    }
);

it('reaches nothing when every line opts out', function (): void {
    $reach = Reach::fromAmounts([['price' => 500, 'takesDiscount' => false]]);

    expect($reach->lines())->toBe(0);
    expect($reach->limit(false))->toBe(0);
    expect($reach->split(200, false)['lines'])->toBe([0 => 0]);
});

it(
    'reads a cart: its lines, what opts out and its delivery',
    function (): void {
        $fulfillment = new CountingFulfillment(475);
        [$cart] = makeCart([$fulfillment]);

        $cart->add(new FakeBuyable('1', 1500), 2);
        $cart->add(new FakeUndiscountedBuyable('2', 1000));
        $cart->setFulfillment($fulfillment);

        $reach = Reach::of($cart);

        expect($reach->lines())->toBe(3000);
        expect($reach->delivery())->toBe(475);
        expect($reach->limit(true))->toBe(3475);
    }
);

it(
    'does not ask the fulfillment its cost until delivery is reached',
    function (): void {
        // A percentage never reaches delivery, so asking would only invite a
        // getCost() that reads the reduction back into a loop.
        $fulfillment = new CountingFulfillment(475);
        [$cart] = makeCart([$fulfillment]);

        $cart->add(new FakeBuyable('1', 1500));
        $cart->setFulfillment($fulfillment);

        $reach = Reach::of($cart);

        expect($reach->lines())->toBe(1500);
        expect($reach->split(100, false)['delivery'])->toBe(0);

        expect($fulfillment->asked)->toBe(0);

        $reach->limit(true);

        expect($fulfillment->asked)->toBe(1);
    }
);

it(
    'reads an order as one line of its subtotal and no delivery',
    function (): void {
        $order = new InMemoryOrder();
        $order->subtotal = 4200;
        $order->fulfillment_cost = 500;

        $reach = Reach::of($order);

        expect($reach->lines())->toBe(4200);
        expect($reach->limit(true))->toBe(4200);
    }
);

it('reaches delivery only for a coupon that says it does', function (
    ?CouponInterface $coupon,
    bool $reaches
): void {
    expect(Reach::reachesDelivery($coupon))->toBe($reaches);
})->with([
    'no coupon' => [null, false],
    'a shop\'s own, silent' => [new FakeCoupon(100), false],
    'a shop\'s own, yes' => [new FakeDeliveryCoupon(100), true],
    'a shop\'s own, no' => [new FakeDeliveryCoupon(100, false), false],
    'percentage' => [Coupon::percentage(10), false],
    'fixed' => [Coupon::fixed(100), true],
]);

it(
    'clamps a coupon to the lines, and delivery when it reaches it',
    function (): void {
        $fulfillment = new CountingFulfillment(500);
        [$cart] = makeCart([$fulfillment]);
        $cart->add(new FakeBuyable('1', 3000));
        $cart->add(new FakeUndiscountedBuyable('2', 800));
        $cart->setFulfillment($fulfillment);

        $reach = Reach::of($cart);

        expect($reach->clamp(new FakeCoupon(99999), $cart))->toBe(3000);
        expect($reach->clamp(new FakeDeliveryCoupon(99999), $cart))->toBe(3500);
        expect($reach->clamp(new FakeDeliveryCoupon(1200), $cart))->toBe(1200);
        expect($reach->clamp(new FakeCoupon(-50), $cart))->toBe(0);
    }
);

it(
    'splits for a coupon as split() does for whether it reaches delivery',
    function (): void {
        $reach = Reach::fromAmounts(
            [
                ['price' => 600, 'takesDiscount' => true],
                ['price' => 900, 'takesDiscount' => false],
            ],
            500
        );

        expect($reach->splitFor(1000, new FakeDeliveryCoupon(0)))->toBe(
            $reach->split(1000, true)
        );
        expect($reach->splitFor(1000, new FakeCoupon(0)))->toBe(
            $reach->split(1000, false)
        );
        expect($reach->splitFor(1000, null))->toBe([
            'lines' => [0 => 600, 1 => 0],
            'delivery' => 0,
        ]);
        expect($reach->splitFor(1000, Coupon::fixed(1000)))->toBe([
            'lines' => [0 => 545, 1 => 0],
            'delivery' => 455,
        ]);
    }
);

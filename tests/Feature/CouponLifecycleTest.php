<?php

declare(strict_types=1);

/*
 * The package's own coupon from cart to paid order, through the real Paid
 * listener — the closure EcommerceServiceProvider registers, reached by
 * booting the provider and dispatching, as CouponRedemptionTest does.
 *
 * InMemoryCoupon keeps the coupon's row in a CouponStore shared by every
 * instance of it, so two orders paying at once are two instances — two
 * requests' worth of loaded rows — writing one balance. What the store cannot
 * show is MySQL doing the same under real concurrency; that is a check
 * against a live database.
 */

use Tests\Support\CouponStore;
use Tests\Support\FakeBuyable;
use Tests\Support\FakeDiscountCode;
use Tests\Support\FakeFulfillment;
use Tests\Support\InMemoryCoupon;
use Tests\Support\InMemoryOrder;
use Tnt\Ecommerce\Events\Order\Paid;

/**
 * An order placed with the coupon, quoted $reduction.
 *
 * @param InMemoryCoupon $coupon
 * @param int $id
 * @param int $reduction
 * @return InMemoryOrder
 */
function orderPlacedWith(
    InMemoryCoupon $coupon,
    int $id,
    int $reduction
): InMemoryOrder {
    $order = new InMemoryOrder();
    $order->id = $id;
    $order->total = 10000 - $reduction;
    $order->reduction = $reduction;
    $order->discount = FakeDiscountCode::forCoupon($coupon);

    return $order;
}

it(
    'honours both quotes when two orders spend one balance at once',
    function (): void {
        // Both carts were quoted 30 against a balance of 50, and both pay. The
        // second payment finds 20 left: the balance stops at 0, the 10 it could
        // not cover is on record, and neither order's total moves.
        $store = new CouponStore();
        $first = InMemoryCoupon::fixed(5000)->keptIn($store);
        $second = $first->loadedAgain();

        $one = orderPlacedWith($first, 1, 3000);
        $two = orderPlacedWith($second, 2, 3000);

        $dispatcher = bootEcommerce();
        $dispatcher->dispatch(Paid::class, new Paid($one));
        $dispatcher->dispatch(Paid::class, new Paid($two));

        expect($store->coupons[(int) $first->id])->toBe([
            'balance' => 0,
            'uses' => 2,
        ]);
        expect($store->redemptions[1]['amount'])->toBe(3000);
        expect($store->redemptions[1]['shortfall'])->toBe(0);
        expect($store->redemptions[2]['amount'])->toBe(3000);
        expect($store->redemptions[2]['shortfall'])->toBe(1000);
        expect($one->getReduction())->toBe(3000);
        expect($two->getReduction())->toBe(3000);
    }
);

it(
    'records a single-use coupon paid for twice as not covered',
    function (): void {
        $store = new CouponStore();
        $first = InMemoryCoupon::percentage(10)->keptIn($store);
        $second = $first->loadedAgain();

        $dispatcher = bootEcommerce();
        $dispatcher->dispatch(
            Paid::class,
            new Paid(orderPlacedWith($first, 1, 500))
        );
        $dispatcher->dispatch(
            Paid::class,
            new Paid(orderPlacedWith($second, 2, 700))
        );

        expect($store->coupons[(int) $first->id]['uses'])->toBe(2);
        expect($store->redemptions[1]['shortfall'])->toBe(0);
        expect($store->redemptions[2]['shortfall'])->toBe(700);
    }
);

it('redeems nothing on a second Paid for the same order', function (): void {
    $store = new CouponStore();
    $coupon = InMemoryCoupon::fixed(5000)->keptIn($store);
    $order = orderPlacedWith($coupon, 1, 3000);

    $dispatcher = bootEcommerce();
    $dispatcher->dispatch(Paid::class, new Paid($order));
    $dispatcher->dispatch(Paid::class, new Paid($order));

    expect($store->coupons[(int) $coupon->id])->toBe([
        'balance' => 2000,
        'uses' => 1,
    ]);
    expect($store->redemptions)->toHaveCount(1);
});

it(
    'does not apply a coupon spent before the order is placed',
    function (): void {
        [$cart] = makeCheckoutCart();
        $coupon = InMemoryCoupon::fixed(5000)->keptIn(new CouponStore());

        $cart->add(new FakeBuyable('1', 4000));
        $cart->addDiscount(FakeDiscountCode::forCoupon($coupon));

        expect($cart->getReduction())->toBe(4000);

        // Another order spent it while this cart was open.
        $coupon->balance = 0;

        $order = $cart->checkout();

        expect($order->getReduction())->toBe(0);
        expect($cart->placed()->discount)->toBeNull();
        expect($order->getTotal())->toBe(4000);
    }
);

it(
    'freezes the part of the reduction that came off delivery',
    function (): void {
        $fulfillment = new FakeFulfillment('post', 1000);
        [$cart] = makeCheckoutCart([$fulfillment]);
        $coupon = InMemoryCoupon::fixed(2000)->keptIn(new CouponStore());

        $cart->add(new FakeBuyable('1', 3000));
        $cart->setFulfillment($fulfillment);
        $cart->addDiscount(FakeDiscountCode::forCoupon($coupon));
        $cart->checkout();

        $order = $cart->placed();

        // 2000 over [3000, 1000].
        expect($order->getReduction())->toBe(2000);
        expect($order->getFulfillmentReduction())->toBe(500);
        expect($order->getTotal())->toBe(2000);
    }
);

it('freezes no delivery reduction for a percentage coupon', function (): void {
    $fulfillment = new FakeFulfillment('post', 1000);
    [$cart] = makeCheckoutCart([$fulfillment]);
    $coupon = InMemoryCoupon::percentage(10)->keptIn(new CouponStore());

    $cart->add(new FakeBuyable('1', 3000));
    $cart->setFulfillment($fulfillment);
    $cart->addDiscount(FakeDiscountCode::forCoupon($coupon));
    $cart->checkout();

    expect($cart->placed()->getReduction())->toBe(300);
    expect($cart->placed()->fulfillment_reduction)->toBe(0);
});

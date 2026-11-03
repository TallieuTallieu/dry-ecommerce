<?php

declare(strict_types=1);

/*
 * What an order says about the discount it was placed with, also once the
 * code row or the coupon behind it is gone.
 */

use dry\db\FetchException;
use Tests\Support\FakeDiscountCode;
use Tests\Support\InMemoryCoupon;
use Tnt\Ecommerce\Model\Order;

/**
 * An order whose code row was deleted after placement: dry's ORM throws when
 * the relation loads it.
 *
 * @param int $fulfillmentReduction
 * @return Order
 */
function orderWithDeletedCode(int $fulfillmentReduction): Order
{
    $order = new class extends Order {
        public function __get($property)
        {
            if ($property === 'discount') {
                throw new FetchException('No row');
            }

            return parent::__get($property);
        }
    };
    $order->fulfillment_reduction = $fulfillmentReduction;

    return $order;
}

it('answers the code it was placed with', function (): void {
    $coupon = InMemoryCoupon::percentage(10);
    $order = new Order();
    $order->discount = FakeDiscountCode::forCoupon(
        $coupon->keptIn($coupon->store()),
        'SUMMER10'
    );

    expect($order->getDiscountCode())->toBe('SUMMER10');
});

it('answers no code without one, or once its row is gone', function (): void {
    expect((new Order())->getDiscountCode())->toBeNull();
    expect(orderWithDeletedCode(0)->getDiscountCode())->toBeNull();
});

it('reads delivery off the coupon while it exists', function (
    InMemoryCoupon $coupon,
    bool $reached
): void {
    $order = new Order();
    $order->discount = FakeDiscountCode::forCoupon(
        $coupon->keptIn($coupon->store())
    );

    // What was frozen does not overrule a coupon that can still say.
    $order->fulfillment_reduction = $reached ? 0 : 500;

    expect($order->discountReachedDelivery())->toBe($reached);
})->with([
    'fixed' => [fn(): InMemoryCoupon => InMemoryCoupon::fixed(1000), true],
    'percentage' => [
        fn(): InMemoryCoupon => InMemoryCoupon::percentage(10),
        false,
    ],
]);

it('falls back on the frozen share without a coupon', function (): void {
    $order = new Order();
    $order->discount = FakeDiscountCode::withoutCoupon();
    $order->fulfillment_reduction = 500;

    expect($order->discountReachedDelivery())->toBeTrue();
    expect(orderWithDeletedCode(500)->discountReachedDelivery())->toBeTrue();
    expect(orderWithDeletedCode(0)->discountReachedDelivery())->toBeFalse();
    expect((new Order())->discountReachedDelivery())->toBeFalse();
});

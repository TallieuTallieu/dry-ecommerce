<?php

declare(strict_types=1);

/*
 * Order::isRePlaceable() — the one spelling of "place() would accept this
 * existing order again".
 *
 * Project code used to re-derive the rule (state placed, money never
 * arrived) at every "try again" button; now the order answers it itself, and
 * Cart::place()'s own guard reads through the same method — that delegation
 * is pinned in PlaceOrderTest. A plain Order answers from its columns with no
 * connection anywhere near it, like the rest of tests/Unit.
 */

use Tnt\Ecommerce\Model\Order;
use Tnt\Ecommerce\Order\OrderState;
use Tnt\Ecommerce\Payment\PaymentStatus;

/**
 * An order as its two lifecycle columns leave it.
 *
 * @param string $state
 * @param string $paymentStatus
 * @return Order
 */
function orderIn(string $state, string $paymentStatus): Order
{
    $order = new Order();
    $order->state = $state;
    $order->payment_status = $paymentStatus;

    return $order;
}

it('re-places exactly the placed-but-unpaid orders', function (
    string $status,
    bool $expected
): void {
    // The same transition rule the webhook listeners write through:
    // canTransitionTo(Pending). Paid refuses (re-freezing would rewrite what
    // the money arrived for) and refunded refuses (its money already went
    // back once); everything short of money arriving is a retry.
    expect(orderIn(OrderState::Placed->value, $status)->isRePlaceable())->toBe(
        $expected
    );
})->with([
    'pending' => [PaymentStatus::Pending->value, true],
    'failed' => [PaymentStatus::Failed->value, true],
    'canceled' => [PaymentStatus::Canceled->value, true],
    'expired' => [PaymentStatus::Expired->value, true],
    'paid' => [PaymentStatus::Paid->value, false],
    'refunded' => [PaymentStatus::Refunded->value, false],
]);

it('never calls a draft re-placeable', function (): void {
    // A draft is placeable — Cart::place() takes it — but not RE-placeable:
    // it has no placement to repeat.
    expect(
        orderIn(
            OrderState::Draft->value,
            PaymentStatus::Pending->value
        )->isRePlaceable()
    )->toBeFalse();
});

it('reads a legacy row as re-placeable', function (): void {
    // Pre-lifecycle rows hold '' in both columns: state reads placed (every
    // such order was real) and payment reads pending (the status that claims
    // nothing) — so a legacy unpaid order can be re-placed like any other.
    expect(orderIn('', '')->isRePlaceable())->toBeTrue();
});

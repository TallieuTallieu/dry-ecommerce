<?php

declare(strict_types=1);

/*
 * Order::isRePlaceable() — the one spelling of "place() would accept this
 * existing order again".
 *
 * Project code used to re-derive the rule at every "try again" button; now
 * the order answers it itself from the payment ledger, and Cart::place()'s
 * own guard reads through the same method — that delegation is pinned in
 * PlaceOrderTest. The entries are handed to an InMemoryOrder directly, so no
 * connection is anywhere near.
 */

use Tests\Support\InMemoryOrder;
use Tnt\Ecommerce\Model\Order;
use Tnt\Ecommerce\Model\PaymentEntry;
use Tnt\Ecommerce\Order\OrderState;
use Tnt\Ecommerce\Payment\EntryKind;

/**
 * A placed order whose ledger holds these money movements.
 *
 * @param array<int, array{EntryKind, int}> $movements
 * @return InMemoryOrder
 */
function orderWithMoney(array $movements): InMemoryOrder
{
    $order = new InMemoryOrder();
    $order->state = OrderState::Placed->value;

    foreach ($movements as [$kind, $amount]) {
        $entry = new PaymentEntry();
        $entry->kind = $kind->value;
        $entry->amount = $amount;
        $order->keepPaymentEntry($entry);
    }

    return $order;
}

it('re-places exactly the orders nobody paid for', function (
    array $movements,
    bool $expected
): void {
    // No capture: every attempt so far came to nothing. Every cent captured
    // went back: nobody has paid for it any more. Anything else is an order
    // somebody paid for, and re-freezing it would rewrite what they paid for.
    expect(orderWithMoney($movements)->isRePlaceable())->toBe($expected);
})->with([
    'no money yet' => [[], true],
    'paid' => [[[EntryKind::Captured, 10000]], false],
    'partially refunded' => [
        [[EntryKind::Captured, 10000], [EntryKind::Refunded, 100]],
        false,
    ],
    'refunded' => [
        [[EntryKind::Captured, 10000], [EntryKind::Refunded, 10000]],
        true,
    ],
    'charged back' => [
        [[EntryKind::Captured, 10000], [EntryKind::Chargeback, 10000]],
        true,
    ],
    'free and paid' => [[[EntryKind::Captured, 0]], false],
]);

it('never calls a draft re-placeable', function (): void {
    // A draft is placeable — Cart::place() takes it — but not RE-placeable:
    // it has no placement to repeat.
    $order = orderWithMoney([]);
    $order->state = OrderState::Draft->value;

    expect($order->isRePlaceable())->toBeFalse();
});

it('reads a legacy row as re-placeable', function (): void {
    // Pre-lifecycle rows hold '' in state and have no ledger entries: state
    // reads placed and nothing was captured. An order never saved has no
    // entries, so a plain Order answers without a query.
    $order = new Order();
    $order->state = '';

    expect($order->isRePlaceable())->toBeTrue();
});

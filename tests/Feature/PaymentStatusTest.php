<?php

declare(strict_types=1);

/*
 * The payment status, derived from the ledger.
 *
 * `ecommerce_order.payment_status` is no longer written by listeners from
 * whatever event arrived last: PaymentLedger derives it from the entries —
 * money first, the current attempt's last reported status otherwise — and
 * dispatches the matching event only when the derived word changes. What used
 * to be a transition guard (a late `expired` must not unsay `paid`, a refund
 * cannot be talked back) now falls out of the derivation, and is held here.
 *
 * bootEcommerce() wires the production Paid listeners; the ledger dispatches
 * through the same dispatcher. Everything keeps to memory through
 * InMemoryOrder and InMemoryPaymentLedger.
 */

use Oak\Dispatcher\Dispatcher;
use Tests\Support\FakeBuyable;
use Tests\Support\FakeRedirector;
use Tests\Support\InMemoryOrder;
use Tests\Support\InMemoryOrderCart;
use Tests\Support\InMemoryPaymentLedger;
use Tests\Support\UnsavedCustomer;
use Tnt\Ecommerce\Account\GuestUserResolver;
use Tnt\Ecommerce\Cart\InMemoryCartStorage;
use Tnt\Ecommerce\Events\Order\Paid;
use Tnt\Ecommerce\Events\Order\PaymentCanceled;
use Tnt\Ecommerce\Events\Order\PaymentExpired;
use Tnt\Ecommerce\Events\Order\PaymentFailed;
use Tnt\Ecommerce\Events\Order\PaymentPartiallyRefunded;
use Tnt\Ecommerce\Events\Order\PaymentRefunded;
use Tnt\Ecommerce\Fulfillment\InMemoryAttributeStorage;
use Tnt\Ecommerce\Order\OrderState;
use Tnt\Ecommerce\Payment\EntryKind;
use Tnt\Ecommerce\Payment\Movement;
use Tnt\Ecommerce\Payment\NullPayment;
use Tnt\Ecommerce\Payment\PaymentRedirect;
use Tnt\Ecommerce\Payment\PaymentStatus;
use Tnt\Ecommerce\Shop\Shop;

/**
 * A placed €100 order with one attempt started, and the ledger that holds it.
 * The dispatcher's events are counted per class into $heard.
 *
 * @param array<string, int> $heard
 * @return array{InMemoryOrder, InMemoryPaymentLedger}
 */
function orderWithAttempt(array &$heard = []): array
{
    $dispatcher = new Dispatcher();

    foreach (
        [
            Paid::class,
            PaymentFailed::class,
            PaymentCanceled::class,
            PaymentExpired::class,
            PaymentRefunded::class,
            PaymentPartiallyRefunded::class,
        ]
        as $event
    ) {
        $dispatcher->addListener($event, function () use (
            &$heard,
            $event
        ): void {
            $heard[$event] = ($heard[$event] ?? 0) + 1;
        });
    }

    $ledger = new InMemoryPaymentLedger($dispatcher);

    $order = new InMemoryOrder();
    $order->state = OrderState::Placed->value;
    $order->total = 10000;
    $order->save();

    $ledger->start(
        $order,
        'fake',
        new PaymentRedirect('tr_1', 'https://pay.example/tr_1')
    );

    return [$order, $ledger];
}

it('gives every order a pending status from birth', function (): void {
    // makeCheckoutCart() pays through FakePayment, which answers with a
    // redirect — an asynchronous gateway whose webhook has not arrived yet.
    // Pending is what the order holds in that window.
    bootEcommerce();

    [$cart, , , , $ledger] = makeCheckoutCart();

    $cart->add(new FakeBuyable('1', 2000));
    $cart->checkout(new UnsavedCustomer());

    expect($cart->placed()->payment_status)->toBe('pending');
    expect($cart->placed()->getPaymentStatus())->toBe(PaymentStatus::Pending);
    expect($ledger->kinds())->toBe(['attempt_started']);
});

it('takes a status report without money as the order status', function (
    PaymentStatus $reported,
    string $event
): void {
    $heard = [];
    [$order, $ledger] = orderWithAttempt($heard);

    reportOn($ledger, $order, $reported);

    expect($order->getPaymentStatus())->toBe($reported);
    expect($heard)->toBe([$event => 1]);
})->with([
    'failed' => [PaymentStatus::Failed, PaymentFailed::class],
    'canceled' => [PaymentStatus::Canceled, PaymentCanceled::class],
    'expired' => [PaymentStatus::Expired, PaymentExpired::class],
]);

it('reads paid from a capture', function (): void {
    $heard = [];
    [$order, $ledger] = orderWithAttempt($heard);

    reportOn($ledger, $order, PaymentStatus::Paid, [
        new Movement(EntryKind::Captured, 'cap_1', 10000),
    ]);

    expect($order->getPaymentStatus())->toBe(PaymentStatus::Paid);
    expect($heard)->toBe([Paid::class => 1]);
});

it('ends a NullPayment checkout paid', function (): void {
    // The shipped dummy gateway, run exactly as a shop that never configured
    // ecommerce.payment runs it: pay() answers settled with a capture of the
    // total, the ledger writes it, and the derived status is paid before
    // checkout() returns — with Paid dispatched to the real listeners.
    $dispatcher = bootEcommerce();
    $ledger = new InMemoryPaymentLedger($dispatcher);
    $redirector = new FakeRedirector();
    $paid = 0;

    $dispatcher->addListener(Paid::class, function () use (&$paid): void {
        $paid++;
    });

    $cart = new InMemoryOrderCart(
        new Shop(new InMemoryAttributeStorage()),
        new InMemoryCartStorage(),
        new NullPayment(),
        new GuestUserResolver(),
        $ledger,
        $redirector
    );

    $cart->add(new FakeBuyable('1', 2000));
    $cart->checkout(new UnsavedCustomer());

    $order = $cart->placed();

    expect($order->getPaymentStatus())->toBe(PaymentStatus::Paid);
    expect($order->getPaid())->toBe(2000);
    expect($ledger->kinds())->toBe([
        'attempt_started',
        'captured',
        'status_reported',
    ]);
    expect($paid)->toBe(1);

    // Settled, so no redirect: the project's controller sends the visitor on.
    expect($redirector->sentTo)->toBe([]);
});

it('mints a fresh NullPayment id per placement', function (): void {
    $payment = new NullPayment();
    $order = new InMemoryOrder();

    $first = $payment->pay($order);
    $second = $payment->pay($order);

    assert($first instanceof Tnt\Ecommerce\Payment\PaymentSettled);
    assert($second instanceof Tnt\Ecommerce\Payment\PaymentSettled);

    expect($payment->provider())->toBe('null');
    expect($first->report->paymentId)->not->toBe($second->report->paymentId);
});

it('keeps a free order paid and not re-placeable', function (): void {
    // A €0 capture is still a capture: the free order is paid for.
    $dispatcher = new Dispatcher();
    $ledger = new InMemoryPaymentLedger($dispatcher);

    $cart = new InMemoryOrderCart(
        new Shop(new InMemoryAttributeStorage()),
        new InMemoryCartStorage(),
        new NullPayment(),
        new GuestUserResolver(),
        $ledger,
        new FakeRedirector()
    );

    $cart->add(new FakeBuyable('1', 0));
    $cart->checkout();

    $order = $cart->placed();

    expect($order->getPaymentStatus())->toBe(PaymentStatus::Paid);
    expect($order->getPaid())->toBe(0);
    expect($order->isRePlaceable())->toBeFalse();
});

it('reads an order from before the lifecycle as pending', function (): void {
    // A row written before anything set payment_status holds ''. It reads as
    // pending because pending is the one status that claims nothing.
    $order = new InMemoryOrder();

    expect($order->getPaymentStatus())->toBe(PaymentStatus::Pending);
});

it('reads a word it does not know as pending', function (): void {
    $order = new InMemoryOrder();
    $order->payment_status = 'authorised';

    expect($order->getPaymentStatus())->toBe(PaymentStatus::Pending);
});

it('keeps a paid order paid through a late status', function (
    PaymentStatus $late
): void {
    // Webhooks arrive at least once and out of order. Once money is
    // captured, money decides: a straggling status is recorded and ignored.
    $heard = [];
    [$order, $ledger] = orderWithAttempt($heard);

    reportOn($ledger, $order, PaymentStatus::Paid, [
        new Movement(EntryKind::Captured, 'cap_1', 10000),
    ]);
    reportOn($ledger, $order, $late, [
        new Movement(EntryKind::Captured, 'cap_1', 10000),
    ]);

    expect($order->getPaymentStatus())->toBe(PaymentStatus::Paid);
    expect($heard)->toBe([Paid::class => 1]);
})->with([
    'a late failure' => [PaymentStatus::Failed],
    'a late cancel' => [PaymentStatus::Canceled],
    'a late expiry' => [PaymentStatus::Expired],
    'a replayed pending' => [PaymentStatus::Pending],
]);

it('takes a partial refund without ending the order', function (): void {
    // €100 captured, €1 back: partially refunded, €99 kept, and not
    // re-placeable — most of the money is still here.
    $heard = [];
    [$order, $ledger] = orderWithAttempt($heard);

    reportOn($ledger, $order, PaymentStatus::Paid, [
        new Movement(EntryKind::Captured, 'cap_1', 10000),
        new Movement(EntryKind::Refunded, 're_1', 100),
    ]);

    expect($order->getPaymentStatus())->toBe(PaymentStatus::PartiallyRefunded);
    expect($order->getNet())->toBe(9900);
    expect($order->isRePlaceable())->toBeFalse();
    expect($heard)->toBe([PaymentPartiallyRefunded::class => 1]);
});

it('lets a partial refund deepen into a full one', function (): void {
    // Then the other €99 goes back: refunded, and re-placeable.
    $heard = [];
    [$order, $ledger] = orderWithAttempt($heard);

    reportOn($ledger, $order, PaymentStatus::Paid, [
        new Movement(EntryKind::Captured, 'cap_1', 10000),
        new Movement(EntryKind::Refunded, 're_1', 100),
    ]);
    reportOn($ledger, $order, PaymentStatus::Paid, [
        new Movement(EntryKind::Captured, 'cap_1', 10000),
        new Movement(EntryKind::Refunded, 're_1', 100),
        new Movement(EntryKind::Refunded, 're_2', 9900),
    ]);

    expect($order->getPaymentStatus())->toBe(PaymentStatus::Refunded);
    expect($order->getNet())->toBe(0);
    expect($order->isRePlaceable())->toBeTrue();
    expect($heard)->toBe([
        PaymentPartiallyRefunded::class => 1,
        PaymentRefunded::class => 1,
    ]);
});

it('lets no late status talk a refund back', function (
    PaymentStatus $late
): void {
    [$order, $ledger] = orderWithAttempt();

    $money = [
        new Movement(EntryKind::Captured, 'cap_1', 10000),
        new Movement(EntryKind::Refunded, 're_1', 10000),
    ];

    reportOn($ledger, $order, PaymentStatus::Refunded, $money);
    reportOn($ledger, $order, $late, $money);

    expect($order->getPaymentStatus())->toBe(PaymentStatus::Refunded);
})->with([
    'paid again' => [PaymentStatus::Paid],
    'failed' => [PaymentStatus::Failed],
    'canceled' => [PaymentStatus::Canceled],
    'expired' => [PaymentStatus::Expired],
]);

it('lets a failed payment be retried into paid', function (): void {
    // Without money the latest report decides, so failed is no dead end.
    [$order, $ledger] = orderWithAttempt();

    reportOn($ledger, $order, PaymentStatus::Failed);
    reportOn($ledger, $order, PaymentStatus::Paid, [
        new Movement(EntryKind::Captured, 'cap_1', 10000),
    ]);

    expect($order->getPaymentStatus())->toBe(PaymentStatus::Paid);
});

it(
    'counts chargebacks and their reversals as money returned',
    function (): void {
        [$order, $ledger] = orderWithAttempt();

        reportOn($ledger, $order, PaymentStatus::Paid, [
            new Movement(EntryKind::Captured, 'cap_1', 10000),
            new Movement(EntryKind::Chargeback, 'chb_1', 10000),
        ]);

        expect($order->getPaymentStatus())->toBe(PaymentStatus::Refunded);

        reportOn($ledger, $order, PaymentStatus::Paid, [
            new Movement(EntryKind::Captured, 'cap_1', 10000),
            new Movement(EntryKind::Chargeback, 'chb_1', 10000),
            new Movement(EntryKind::ChargebackReversed, 'chb_1', 10000),
        ]);

        expect($order->getPaymentStatus())->toBe(PaymentStatus::Paid);
        expect($order->getReturned())->toBe(0);
    }
);

it(
    'saves nothing and dispatches nothing when the status holds',
    function (): void {
        $heard = [];
        [$order, $ledger] = orderWithAttempt($heard);

        reportOn($ledger, $order, PaymentStatus::Failed);
        $saves = $order->saveCount;

        $ledger->recompute($order);

        expect($order->saveCount)->toBe($saves);
        expect($heard)->toBe([PaymentFailed::class => 1]);
    }
);

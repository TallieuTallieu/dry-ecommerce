<?php

declare(strict_types=1);

/*
 * How an order was paid. A gateway reports the method (and a card's brand)
 * on its PaymentReport; the ledger keeps both on the `captured` entry it
 * writes, and the order reads them back off its latest capture. A free
 * order's €0 capture reads as `free` without anybody storing it.
 */

use Oak\Dispatcher\Dispatcher;
use Tests\Support\FakeBuyable;
use Tests\Support\FakeRedirector;
use Tests\Support\InMemoryOrder;
use Tests\Support\InMemoryOrderCart;
use Tests\Support\InMemoryPaymentLedger;
use Tnt\Ecommerce\Account\GuestUserResolver;
use Tnt\Ecommerce\Cart\InMemoryCartStorage;
use Tnt\Ecommerce\Fulfillment\InMemoryAttributeStorage;
use Tnt\Ecommerce\Model\Order;
use Tnt\Ecommerce\Order\OrderState;
use Tnt\Ecommerce\Payment\EntryKind;
use Tnt\Ecommerce\Payment\Movement;
use Tnt\Ecommerce\Payment\NullPayment;
use Tnt\Ecommerce\Payment\PaymentRedirect;
use Tnt\Ecommerce\Payment\PaymentReport;
use Tnt\Ecommerce\Payment\PaymentStatus;
use Tnt\Ecommerce\Shop\Shop;

/**
 * A placed €100 order with attempt tr_1 started, and its ledger.
 *
 * @return array{InMemoryOrder, InMemoryPaymentLedger}
 */
function orderAwaitingMethod(): array
{
    $ledger = new InMemoryPaymentLedger(new Dispatcher());

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

/**
 * A paid report for $paymentId: €100 captured under its own id.
 *
 * @param string $paymentId
 * @param string|null $method
 * @param string|null $cardLabel
 * @return PaymentReport
 */
function paidWith(
    string $paymentId,
    ?string $method,
    ?string $cardLabel = null
): PaymentReport {
    return new PaymentReport(
        $paymentId,
        PaymentStatus::Paid,
        [new Movement(EntryKind::Captured, $paymentId, 10000)],
        $method,
        $cardLabel
    );
}

/**
 * The ledger's `captured` entries, in order.
 *
 * @param InMemoryPaymentLedger $ledger
 * @return list<\Tnt\Ecommerce\Model\PaymentEntry>
 */
function capturesIn(InMemoryPaymentLedger $ledger): array
{
    return array_values(
        array_filter(
            $ledger->written,
            static fn($entry): bool => $entry->kind ===
                EntryKind::Captured->value
        )
    );
}

it('keeps the reported method on the capture', function (): void {
    [$order, $ledger] = orderAwaitingMethod();

    $ledger->apply($order, 'fake', paidWith('tr_1', 'bancontact'));

    expect(capturesIn($ledger)[0]->method)->toBe('bancontact');
    expect($order->getPaymentMethod())->toBe('bancontact');
    expect($order->getPaymentCardLabel())->toBeNull();
});

it('keeps the card label beside the method', function (): void {
    [$order, $ledger] = orderAwaitingMethod();

    $ledger->apply(
        $order,
        'fake',
        paidWith('tr_1', 'creditcard', 'Mastercard')
    );

    expect(capturesIn($ledger)[0]->getMethod())->toBe('creditcard');
    expect(capturesIn($ledger)[0]->getCardLabel())->toBe('Mastercard');
    expect($order->getPaymentMethod())->toBe('creditcard');
    expect($order->getPaymentCardLabel())->toBe('Mastercard');
});

it('reads no method when the gateway reports none', function (): void {
    // A gateway that does not say, and every order paid before the ledger
    // kept a method: paid, with nothing to tell how.
    [$order, $ledger] = orderAwaitingMethod();

    $ledger->apply($order, 'fake', paidWith('tr_1', null));

    expect($order->getPaymentStatus())->toBe(PaymentStatus::Paid);
    expect(capturesIn($ledger)[0]->method)->toBeNull();
    expect($order->getPaymentMethod())->toBeNull();
    expect($order->getPaymentCardLabel())->toBeNull();
});

it('reads an empty method as none', function (): void {
    [$order, $ledger] = orderAwaitingMethod();

    $ledger->apply($order, 'fake', paidWith('tr_1', '', ' '));

    expect(capturesIn($ledger)[0]->method)->toBeNull();
    expect(capturesIn($ledger)[0]->card_label)->toBeNull();
    expect($order->getPaymentMethod())->toBeNull();
});

it('reads no method before anything was captured', function (): void {
    // Mollie names a method as soon as the visitor picks one; without money
    // the ledger has no capture to keep it on.
    [$order, $ledger] = orderAwaitingMethod();

    $ledger->apply(
        $order,
        'fake',
        new PaymentReport('tr_1', PaymentStatus::Failed, [], 'bancontact')
    );

    expect($ledger->kinds())->toBe(['attempt_started', 'status_reported']);
    expect($ledger->written[1]->method)->toBeNull();
    expect($order->getPaymentMethod())->toBeNull();
});

it('keeps the method only on the capture', function (): void {
    // A refund in the same report goes back the way the money came; it
    // carries no method of its own.
    [$order, $ledger] = orderAwaitingMethod();

    $ledger->apply(
        $order,
        'fake',
        new PaymentReport(
            'tr_1',
            PaymentStatus::Paid,
            [
                new Movement(EntryKind::Captured, 'tr_1', 10000),
                new Movement(EntryKind::Refunded, 're_1', 1000),
            ],
            'ideal'
        )
    );

    $methods = [];

    foreach ($ledger->written as $entry) {
        $methods[$entry->kind] = $entry->method;
    }

    expect($methods)->toBe([
        'attempt_started' => null,
        'captured' => 'ideal',
        'refunded' => null,
        'status_reported' => null,
    ]);
    expect($order->getPaymentStatus())->toBe(PaymentStatus::PartiallyRefunded);
    expect($order->getPaymentMethod())->toBe('ideal');
});

it('writes the method once, with the capture', function (): void {
    // Append-only: a replay changes nothing, and neither does a later report
    // naming another method for a capture already written.
    [$order, $ledger] = orderAwaitingMethod();

    $ledger->apply($order, 'fake', paidWith('tr_1', 'bancontact'));
    $ledger->apply($order, 'fake', paidWith('tr_1', 'bancontact'));
    $ledger->apply($order, 'fake', paidWith('tr_1', 'ideal'));

    expect(capturesIn($ledger))->toHaveCount(1);
    expect($order->getPaymentMethod())->toBe('bancontact');
});

it('reads the method of the latest capture', function (): void {
    // A late capture on a superseded attempt lands after the current one's:
    // the order answers the latest; each entry keeps its own.
    [$order, $ledger] = orderAwaitingMethod();

    $order->payment_id = 'tr_2';
    $ledger->apply($order, 'fake', paidWith('tr_2', 'creditcard', 'Visa'));
    $ledger->apply($order, 'fake', paidWith('tr_1', 'bancontact'));

    expect(
        array_map(
            static fn($entry): ?string => $entry->getMethod(),
            capturesIn($ledger)
        )
    )->toBe(['creditcard', 'bancontact']);
    expect($order->getPaymentMethod())->toBe('bancontact');
    expect($order->getPaymentCardLabel())->toBeNull();
});

it('reads a free order as free', function (): void {
    // NullPayment settles €0 with no method — as dry-mollie does a free
    // order. The €0 capture is what says free; nothing stores the word.
    $ledger = new InMemoryPaymentLedger(new Dispatcher());

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
    expect(capturesIn($ledger)[0]->method)->toBeNull();
    expect($order->getPaymentMethod())->toBe(Order::PAYMENT_METHOD_FREE);
    expect($order->getPaymentMethod())->toBe('free');
    expect($order->getPaymentCardLabel())->toBeNull();
});

it('reads a paid NullPayment order as no method', function (): void {
    // Money captured without a method is not free; it is unknown.
    $ledger = new InMemoryPaymentLedger(new Dispatcher());

    $cart = new InMemoryOrderCart(
        new Shop(new InMemoryAttributeStorage()),
        new InMemoryCartStorage(),
        new NullPayment(),
        new GuestUserResolver(),
        $ledger,
        new FakeRedirector()
    );

    $cart->add(new FakeBuyable('1', 2000));
    $cart->checkout();

    expect($cart->placed()->getPaymentMethod())->toBeNull();
});

it('reads an unpaid order as no method', function (): void {
    [$order] = orderAwaitingMethod();

    expect($order->getPaymentMethod())->toBeNull();
    expect($order->getPaymentCardLabel())->toBeNull();
});

it('builds a report the way gateways before the method did', function (): void {
    // Positional, three arguments: the method and card label are optional,
    // so a gateway that never heard of them keeps working.
    $report = new PaymentReport('tr_1', PaymentStatus::Paid, [
        new Movement(EntryKind::Captured, 'tr_1', 10000),
    ]);

    expect($report->method)->toBeNull();
    expect($report->cardLabel)->toBeNull();

    $named = new PaymentReport(
        paymentId: 'tr_1',
        status: PaymentStatus::Paid,
        method: 'creditcard',
        cardLabel: 'Visa'
    );

    expect($named->movements)->toBe([]);
    expect($named->method)->toBe('creditcard');
    expect($named->cardLabel)->toBe('Visa');
});

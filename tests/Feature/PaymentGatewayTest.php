<?php

declare(strict_types=1);

/*
 * The payment provider harness — the shape every real gateway sits on.
 *
 * A gateway's two halves run here against an in-memory provider: pay(),
 * which creates the provider-side payment and answers what it did, and the
 * webhook, where the package's PaymentWebhook finds the order through the
 * ledger, asks the gateway for its report, and hands it to PaymentLedger. The
 * gateway reports; the package writes — the entries, `payment_id`,
 * `payment_status` and the redirect.
 */

use Oak\Dispatcher\Dispatcher;
use Tests\Support\FakeBuyable;
use Tests\Support\FakeGateway;
use Tests\Support\FakeRedirector;
use Tests\Support\InMemoryOrder;
use Tests\Support\InMemoryOrderCart;
use Tests\Support\InMemoryPaymentLedger;
use Tests\Support\InMemoryPaymentWebhook;
use Tnt\Ecommerce\Account\GuestUserResolver;
use Tnt\Ecommerce\Cart\InMemoryCartStorage;
use Tnt\Ecommerce\Events\Order\PaymentFailed;
use Tnt\Ecommerce\Fulfillment\InMemoryAttributeStorage;
use Tnt\Ecommerce\Order\OrderState;
use Tnt\Ecommerce\Payment\EntryKind;
use Tnt\Ecommerce\Payment\Movement;
use Tnt\Ecommerce\Payment\PaymentRefused;
use Tnt\Ecommerce\Payment\PaymentReport;
use Tnt\Ecommerce\Payment\PaymentStatus;
use Tnt\Ecommerce\Shop\Shop;
use Tnt\Ecommerce\UnknownPayment;

beforeEach(function (): void {
    // Cart::place() dispatches Created through the facade.
    $app = new Oak\Container\Container();
    Oak\Facade::setContainer($app);
    $app->singleton(
        Oak\Contracts\Dispatcher\DispatcherInterface::class,
        Oak\Dispatcher\Dispatcher::class
    );
});

/**
 * A cart paying through the fake gateway, with the ledger, the redirector
 * and a webhook handler over the same ledger.
 *
 * @return array{InMemoryOrderCart, FakeGateway, InMemoryPaymentLedger, FakeRedirector, InMemoryPaymentWebhook}
 */
function gatewayCheckout(): array
{
    $gateway = new FakeGateway();
    $ledger = new InMemoryPaymentLedger(new Dispatcher());
    $redirector = new FakeRedirector();

    $cart = new InMemoryOrderCart(
        new Shop(new InMemoryAttributeStorage()),
        new InMemoryCartStorage(),
        $gateway,
        new GuestUserResolver(),
        $ledger,
        $redirector
    );

    $cart->add(new FakeBuyable('1', 10000));

    return [
        $cart,
        $gateway,
        $ledger,
        $redirector,
        new InMemoryPaymentWebhook($gateway, $ledger),
    ];
}

it(
    'records the attempt and redirects to the provider checkout',
    function (): void {
        [$cart, , $ledger, $redirector] = gatewayCheckout();

        $cart->checkout();
        $order = $cart->placed();

        // The package, not the gateway, points the order at its attempt.
        expect($order->payment_id)->toBe('tr_fake_1');
        expect($ledger->kinds())->toBe(['attempt_started']);
        expect($ledger->written[0]->provider)->toBe('fake');
        expect($redirector->sentTo)->toBe([
            'https://pay.example/checkout/tr_fake_1',
        ]);
    }
);

it('resolves a webhook through the ledger', function (
    PaymentReport $report,
    PaymentStatus $expected
): void {
    [$cart, $gateway, , , $webhook] = gatewayCheckout();

    $cart->checkout();
    $gateway->reports['tr_fake_1'] = $report;

    $webhook->handle('tr_fake_1');

    expect($cart->placed()->getPaymentStatus())->toBe($expected);
})->with([
    'paid' => [
        new PaymentReport('tr_fake_1', PaymentStatus::Paid, [
            new Movement(EntryKind::Captured, 'tr_fake_1', 10000),
        ]),
        PaymentStatus::Paid,
    ],
    'failed' => [
        new PaymentReport('tr_fake_1', PaymentStatus::Failed),
        PaymentStatus::Failed,
    ],
    'canceled' => [
        new PaymentReport('tr_fake_1', PaymentStatus::Canceled),
        PaymentStatus::Canceled,
    ],
    'expired' => [
        new PaymentReport('tr_fake_1', PaymentStatus::Expired),
        PaymentStatus::Expired,
    ],
]);

it('writes nothing while the provider still says pending', function (): void {
    [$cart, , $ledger, , $webhook] = gatewayCheckout();

    $cart->checkout();
    $saves = $cart->placed()->saveCount;

    $webhook->handle('tr_fake_1');

    expect($ledger->kinds())->toBe(['attempt_started']);
    expect($cart->placed()->saveCount)->toBe($saves);
});

it('writes one unknown_payment entry, then refuses', function (): void {
    [, , $ledger, , $webhook] = gatewayCheckout();

    expect(fn() => $webhook->handle('tr_never_issued'))->toThrow(
        UnknownPayment::class,
        'tr_never_issued'
    );

    expect($ledger->kinds())->toBe(['unknown_payment']);
    expect($ledger->written[0]->order)->toBeNull();
    expect($ledger->written[0]->payment_id)->toBe('tr_never_issued');
});

it('writes nothing the second time the same report arrives', function (): void {
    [$cart, $gateway, $ledger, , $webhook] = gatewayCheckout();

    $cart->checkout();
    $gateway->reports['tr_fake_1'] = new PaymentReport(
        'tr_fake_1',
        PaymentStatus::Paid,
        [new Movement(EntryKind::Captured, 'tr_fake_1', 10000)]
    );

    $webhook->handle('tr_fake_1');
    $written = $ledger->kinds();
    $saves = $cart->placed()->saveCount;

    $webhook->handle('tr_fake_1');

    expect($written)->toBe(['attempt_started', 'captured', 'status_reported']);
    expect($ledger->kinds())->toBe($written);
    expect($cart->placed()->saveCount)->toBe($saves);
});

it('keeps a paid order paid through a late expired webhook', function (): void {
    [$cart, $gateway, , , $webhook] = gatewayCheckout();

    $cart->checkout();
    $capture = new Movement(EntryKind::Captured, 'tr_fake_1', 10000);

    $gateway->reports['tr_fake_1'] = new PaymentReport(
        'tr_fake_1',
        PaymentStatus::Paid,
        [$capture]
    );
    $webhook->handle('tr_fake_1');

    $gateway->reports['tr_fake_1'] = new PaymentReport(
        'tr_fake_1',
        PaymentStatus::Expired,
        [$capture]
    );
    $webhook->handle('tr_fake_1');

    expect($cart->placed()->getPaymentStatus())->toBe(PaymentStatus::Paid);
});

it('counts a capture on a superseded attempt', function (): void {
    // The customer abandons the first checkout, the order is re-placed, and
    // then the first attempt pays after all. Its webhook still resolves —
    // the ledger knows every attempt — and its money counts.
    [$cart, $gateway, , , $webhook] = gatewayCheckout();

    $cart->checkout();
    $order = $cart->placed();

    $gateway->reports['tr_fake_1'] = new PaymentReport(
        'tr_fake_1',
        PaymentStatus::Canceled
    );
    $webhook->handle('tr_fake_1');

    $cart->place($order);
    expect($order->payment_id)->toBe('tr_fake_2');

    $gateway->reports['tr_fake_1'] = new PaymentReport(
        'tr_fake_1',
        PaymentStatus::Paid,
        [new Movement(EntryKind::Captured, 'tr_fake_1', 10000)]
    );
    $webhook->handle('tr_fake_1');

    expect($order->getPaid())->toBe(10000);
    expect($order->getPaymentStatus())->toBe(PaymentStatus::Paid);
    expect($order->isRePlaceable())->toBeFalse();
});

/**
 * Check out €100, have it captured and refunded in full on tr_fake_1, then
 * re-place the same order — optionally with a different basket first.
 *
 * @param InMemoryOrderCart $cart
 * @param FakeGateway $gateway
 * @param InMemoryPaymentWebhook $webhook
 * @param int|null $newPrice The re-placed basket's single line, or null to
 *                           keep the €100 one.
 * @return InMemoryOrder
 */
function refundedAndRePlaced(
    InMemoryOrderCart $cart,
    FakeGateway $gateway,
    InMemoryPaymentWebhook $webhook,
    ?int $newPrice = null
): InMemoryOrder {
    $cart->checkout();
    $order = $cart->placed();

    $gateway->reports['tr_fake_1'] = new PaymentReport(
        'tr_fake_1',
        PaymentStatus::Refunded,
        [
            new Movement(EntryKind::Captured, 'tr_fake_1', 10000),
            new Movement(EntryKind::Refunded, 're_1', 10000),
        ]
    );
    $webhook->handle('tr_fake_1');

    expect($order->getPaymentStatus())->toBe(PaymentStatus::Refunded);

    if ($newPrice !== null) {
        foreach ($cart->items() as $item) {
            $cart->removeItem($item->getId());
        }

        $cart->add(new FakeBuyable('2', $newPrice));
    }

    $cart->place($order);

    return $order;
}

it('reads a re-placed refunded order by its new attempt', function (): void {
    // D7a (a): the old attempt's refunded money does not describe an order
    // that is waiting on a new attempt — pending does.
    [$cart, $gateway, , , $webhook] = gatewayCheckout();

    $order = refundedAndRePlaced($cart, $gateway, $webhook);
    $webhook->handle('tr_fake_2');

    expect($order->payment_id)->toBe('tr_fake_2');
    expect($order->getPaymentStatus())->toBe(PaymentStatus::Pending);
    expect($order->isRePlaceable())->toBeTrue();
});

it(
    'reads a re-placed refunded order paid once it pays again',
    function (): void {
        // D7a (b): paid 200, returned 100 — but the €100 total is covered, so
        // paid, not partially refunded.
        [$cart, $gateway, , , $webhook] = gatewayCheckout();

        $order = refundedAndRePlaced($cart, $gateway, $webhook);

        $gateway->reports['tr_fake_2'] = new PaymentReport(
            'tr_fake_2',
            PaymentStatus::Paid,
            [new Movement(EntryKind::Captured, 'tr_fake_2', 10000)]
        );
        $webhook->handle('tr_fake_2');

        expect($order->getPaid())->toBe(20000);
        expect($order->getNet())->toBe(10000);
        expect($order->getPaymentStatus())->toBe(PaymentStatus::Paid);
        expect($order->isRePlaceable())->toBeFalse();
    }
);

it(
    'reads a re-placed smaller basket paid once it is covered',
    function (): void {
        // D7a (c): refunded €100 in full, re-placed at €80, paid €80.
        [$cart, $gateway, , , $webhook] = gatewayCheckout();

        $order = refundedAndRePlaced($cart, $gateway, $webhook, 8000);

        expect($order->getTotal())->toBe(8000);

        $gateway->reports['tr_fake_2'] = new PaymentReport(
            'tr_fake_2',
            PaymentStatus::Paid,
            [new Movement(EntryKind::Captured, 'tr_fake_2', 8000)]
        );
        $webhook->handle('tr_fake_2');

        expect($order->getNet())->toBe(8000);
        expect($order->getOutstanding())->toBe(0);
        expect($order->getPaymentStatus())->toBe(PaymentStatus::Paid);
    }
);

it('reads a superseded attempt status as history only', function (): void {
    // Without money only the current attempt speaks for the order.
    [$cart, $gateway, $ledger, , $webhook] = gatewayCheckout();

    $cart->checkout();
    $order = $cart->placed();
    $cart->place($order);

    $gateway->reports['tr_fake_1'] = new PaymentReport(
        'tr_fake_1',
        PaymentStatus::Expired
    );
    $webhook->handle('tr_fake_1');

    expect($ledger->kinds())->toContain('status_reported');
    expect($order->getPaymentStatus())->toBe(PaymentStatus::Pending);
});

it('records a refusal with an id as a failed attempt', function (): void {
    $ledger = new InMemoryPaymentLedger(new Dispatcher());
    $order = placedOrder();

    $ledger->start($order, 'fake', new PaymentRefused('tr_refused'));

    expect($ledger->kinds())->toBe(['attempt_started', 'status_reported']);
    expect($order->getPaymentStatus())->toBe(PaymentStatus::Failed);
    expect($order->isRePlaceable())->toBeTrue();
});

it('fails a refusal without an id and writes no entry', function (): void {
    // No id means no attempt to file anything under: the ledger stays
    // empty, the column says failed, and PaymentFailed still goes out.
    $dispatcher = new Dispatcher();
    $failed = 0;
    $dispatcher->addListener(PaymentFailed::class, function () use (
        &$failed
    ): void {
        $failed++;
    });

    $ledger = new InMemoryPaymentLedger($dispatcher);
    $order = placedOrder();

    $ledger->start($order, 'fake', new PaymentRefused());

    expect($ledger->written)->toBe([]);
    expect($order->getPaymentStatus())->toBe(PaymentStatus::Failed);
    expect($order->isRePlaceable())->toBeTrue();
    expect($failed)->toBe(1);
});

it('does not redirect for a refusal', function (): void {
    $ledger = new InMemoryPaymentLedger(new Dispatcher());
    $redirector = new FakeRedirector();
    $payment = new Tests\Support\FakePayment();
    $payment->outcome = new PaymentRefused();

    $cart = new InMemoryOrderCart(
        new Shop(new InMemoryAttributeStorage()),
        new InMemoryCartStorage(),
        $payment,
        new GuestUserResolver(),
        $ledger,
        $redirector
    );

    $cart->add(new FakeBuyable('1', 10000));
    $cart->checkout();

    expect($redirector->sentTo)->toBe([]);
    expect($cart->placed()->getPaymentStatus())->toBe(PaymentStatus::Failed);
});

it('writes a reversal only once its counterpart exists', function (): void {
    $ledger = new InMemoryPaymentLedger(new Dispatcher());
    $order = placedOrder();
    $order->payment_id = 'tr_1';

    reportOn($ledger, $order, PaymentStatus::Paid, [
        new Movement(EntryKind::Captured, 'cap_1', 10000),
        new Movement(EntryKind::RefundReversed, 're_1', 500),
    ]);

    expect($ledger->kinds())->not->toContain('refund_reversed');

    // Listed before the refund it undoes, in the same report: still found.
    reportOn($ledger, $order, PaymentStatus::Paid, [
        new Movement(EntryKind::Captured, 'cap_1', 10000),
        new Movement(EntryKind::RefundReversed, 're_1', 500),
        new Movement(EntryKind::Refunded, 're_1', 500),
    ]);

    expect($ledger->kinds())->toContain('refund_reversed');
    expect($order->getReturned())->toBe(0);
    expect($order->getPaymentStatus())->toBe(PaymentStatus::Paid);
});

it('answers the order figures from the entries', function (): void {
    $ledger = new InMemoryPaymentLedger(new Dispatcher());
    $order = placedOrder();
    $order->payment_id = 'tr_1';

    expect($order->getOutstanding())->toBe(10000);

    reportOn($ledger, $order, PaymentStatus::Paid, [
        new Movement(EntryKind::Captured, 'cap_1', 6000),
        new Movement(EntryKind::Refunded, 're_1', 1000),
    ]);

    expect($order->getPaid())->toBe(6000);
    expect($order->getReturned())->toBe(1000);
    expect($order->getNet())->toBe(5000);
    expect($order->getOutstanding())->toBe(5000);
});

it(
    'refuses a movement that moves no money or a negative amount',
    function (): void {
        expect(
            fn() => new Movement(EntryKind::StatusReported, 'x', 100)
        )->toThrow(InvalidArgumentException::class);
        expect(fn() => new Movement(EntryKind::Captured, 'x', -1))->toThrow(
            InvalidArgumentException::class
        );
    }
);

/**
 * A placed €100 order with no attempt yet.
 *
 * @return InMemoryOrder
 */
function placedOrder(): InMemoryOrder
{
    $order = new InMemoryOrder();
    $order->state = OrderState::Placed->value;
    $order->total = 10000;
    $order->save();

    return $order;
}

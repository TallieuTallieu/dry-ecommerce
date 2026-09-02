<?php

declare(strict_types=1);

/*
 * The payment provider harness — the shape every real gateway sits on.
 *
 * A gateway's two halves are exercised here against an in-memory provider:
 * pay(), which creates the provider-side payment and answers with a redirect
 * through the RedirectorInterface seam, and the webhook, where the package's
 * PaymentWebhook finds the order by payment id, asks the gateway what its
 * provider says, and dispatches. The listeners under the dispatch are the
 * production ones (bootEcommerce()), because the guard they write through is
 * the harness's whole idempotency story: a replayed or late webhook must die
 * against PaymentStatus::canTransitionTo(), not against gateway cleverness.
 */

use Tests\Support\FakeGateway;
use Tests\Support\FakeRedirector;
use Tests\Support\InMemoryOrder;
use Tests\Support\InMemoryPaymentWebhook;
use Tnt\Ecommerce\Payment\PaymentStatus;
use Tnt\Ecommerce\UnknownPayment;

/**
 * An order the webhook can find: placed, pending, carrying a payment id.
 *
 * @param string $paymentId
 * @return InMemoryOrder
 */
function orderAwaitingPayment(string $paymentId): InMemoryOrder
{
    $order = new InMemoryOrder();
    $order->payment_id = $paymentId;
    $order->payment_status = PaymentStatus::Pending->value;

    return $order;
}

it('answers pay() with a redirect to the provider checkout', function (): void {
    $redirector = new FakeRedirector();
    $gateway = new FakeGateway($redirector);

    $order = new InMemoryOrder();
    $gateway->pay($order);

    expect($order->payment_id)->toBe('tr_fake_1');
    expect($redirector->sentTo)->toBe([
        'https://pay.example/checkout/tr_fake_1',
    ]);
});

it('gives a re-placed order a fresh payment id', function (): void {
    // Re-placement calls pay() again on the same row. The old payment is
    // dead at the provider, so the id is overwritten — a webhook for the
    // old attempt then finds no order, which is the correct answer for it.
    $gateway = new FakeGateway(new FakeRedirector());

    $order = new InMemoryOrder();
    $gateway->pay($order);
    $first = $order->payment_id;

    $gateway->pay($order);

    expect($first)->toBe('tr_fake_1');
    expect($order->payment_id)->toBe('tr_fake_2');
});

it('dispatches the event the provider reports', function (
    PaymentStatus $reported,
    string $written
): void {
    $dispatcher = bootEcommerce();

    $gateway = new FakeGateway(new FakeRedirector());
    $gateway->reports['tr_fake_1'] = $reported;

    $order = orderAwaitingPayment('tr_fake_1');

    $webhook = new InMemoryPaymentWebhook($gateway, $dispatcher);
    $webhook->orders['tr_fake_1'] = $order;

    $webhook->handle('tr_fake_1');

    expect($order->payment_status)->toBe($written);
})->with([
    'paid' => [PaymentStatus::Paid, 'paid'],
    'failed' => [PaymentStatus::Failed, 'failed'],
    'canceled' => [PaymentStatus::Canceled, 'canceled'],
    'expired' => [PaymentStatus::Expired, 'expired'],
    'refunded' => [PaymentStatus::Refunded, 'refunded'],
]);

it(
    'dispatches nothing while the provider still says pending',
    function (): void {
        $dispatcher = bootEcommerce();

        // FakeGateway reports Pending for any id it was not scripted with —
        // the same answer a real gateway gives for a payment still open.
        $gateway = new FakeGateway(new FakeRedirector());

        $order = orderAwaitingPayment('tr_fake_1');

        $webhook = new InMemoryPaymentWebhook($gateway, $dispatcher);
        $webhook->orders['tr_fake_1'] = $order;

        $webhook->handle('tr_fake_1');

        expect($order->payment_status)->toBe('pending');
        expect($order->saveCount)->toBe(0);
    }
);

it('refuses a payment id no order carries', function (): void {
    $dispatcher = bootEcommerce();

    $webhook = new InMemoryPaymentWebhook(
        new FakeGateway(new FakeRedirector()),
        $dispatcher
    );

    $webhook->handle('tr_never_issued');
})->throws(UnknownPayment::class, 'tr_never_issued');

it('keeps a paid order paid through a late expired webhook', function (): void {
    // The provider first says paid, then — a replayed or delayed delivery —
    // expired. The gateway maps honestly both times; the listener's guard is
    // what refuses to unsay that the money arrived.
    $dispatcher = bootEcommerce();

    $gateway = new FakeGateway(new FakeRedirector());
    $order = orderAwaitingPayment('tr_fake_1');

    $webhook = new InMemoryPaymentWebhook($gateway, $dispatcher);
    $webhook->orders['tr_fake_1'] = $order;

    $gateway->reports['tr_fake_1'] = PaymentStatus::Paid;
    $webhook->handle('tr_fake_1');

    $gateway->reports['tr_fake_1'] = PaymentStatus::Expired;
    $webhook->handle('tr_fake_1');

    expect($order->payment_status)->toBe('paid');
});

it('takes a replayed paid webhook as a no-op', function (): void {
    $dispatcher = bootEcommerce();

    $gateway = new FakeGateway(new FakeRedirector());
    $gateway->reports['tr_fake_1'] = PaymentStatus::Paid;

    $order = orderAwaitingPayment('tr_fake_1');

    $webhook = new InMemoryPaymentWebhook($gateway, $dispatcher);
    $webhook->orders['tr_fake_1'] = $order;

    $webhook->handle('tr_fake_1');
    $savesAfterFirst = $order->saveCount;

    $webhook->handle('tr_fake_1');

    expect($order->payment_status)->toBe('paid');

    // The guard blocks paid -> paid, so the replay writes nothing at all.
    expect($order->saveCount)->toBe($savesAfterFirst);
});

it('still refunds after the money arrived', function (): void {
    // The one transition the guard allows out of paid — a refund webhook
    // must land, late or not.
    $dispatcher = bootEcommerce();

    $gateway = new FakeGateway(new FakeRedirector());
    $order = orderAwaitingPayment('tr_fake_1');

    $webhook = new InMemoryPaymentWebhook($gateway, $dispatcher);
    $webhook->orders['tr_fake_1'] = $order;

    $gateway->reports['tr_fake_1'] = PaymentStatus::Paid;
    $webhook->handle('tr_fake_1');

    $gateway->reports['tr_fake_1'] = PaymentStatus::Refunded;
    $webhook->handle('tr_fake_1');

    expect($order->payment_status)->toBe('refunded');
});

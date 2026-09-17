<?php

declare(strict_types=1);

/*
 * Going at the money more than once (sc-11448).
 *
 * An order used to carry a single `payment_id`, which `Cart::place()` never
 * cleared. A re-placed order therefore left its previous payment id belonging
 * to no order at all: when the provider called back about that dead id the
 * host's webhook could only answer 404 — for ever, on the provider's full
 * retry schedule — and a provider that sees an endpoint keep failing disables
 * it, taking the orders that DO matter with it.
 *
 * So: `place()` clears the id and re-mints the key, and every attempt leaves a
 * row behind. The dead id still resolves to its order; its news is recorded
 * against the attempt it was about, and goes no further, because the order has
 * since been re-placed and is owed a different payment.
 *
 * In memory throughout: the attempt rows come through Order's
 * newPaymentAttempt() seam and the webhook's lookups through its own two.
 */

use Tests\Support\FakeBuyable;
use Tests\Support\FakeGateway;
use Tests\Support\FakeRedirector;
use Tests\Support\InMemoryOrder;
use Tests\Support\InMemoryPaymentWebhook;
use Tnt\Ecommerce\Order\OrderState;
use Tnt\Ecommerce\Payment\PaymentStatus;

/**
 * A draft ready to be placed, on a booted package — `place()` announces
 * placement through the Dispatcher facade, which needs the container.
 *
 * @return InMemoryOrder
 */
function placeableDraft(): InMemoryOrder
{
    bootEcommerce();

    $draft = new InMemoryOrder();
    $draft->created = time();
    $draft->updated = time();
    $draft->state = OrderState::Draft->value;
    $draft->save();

    return $draft;
}

it('clears the payment id when an order is placed again', function (): void {
    [$cart] = makeCheckoutCart();

    $order = placeableDraft();

    $cart->add(new FakeBuyable('1', 2000));
    $cart->place($order);

    $order->payment_id = 'tr_dead_1';
    $order->setPaymentStatus(PaymentStatus::Failed);

    $cart->place($order);

    // Not the dead id, and not left standing for the gateway to overwrite:
    // between placement and pay() the order is waiting on nothing.
    expect($order->getPaymentId())->toBeNull();
});

it('re-mints the payment key on every placement', function (): void {
    [$cart] = makeCheckoutCart();

    $order = placeableDraft();

    $cart->add(new FakeBuyable('1', 2000));
    $cart->place($order);

    $first = $order->getPaymentKey();

    $order->setPaymentStatus(PaymentStatus::Failed);
    $cart->place($order);

    // Unlike `order_id`, which a re-placed order deliberately keeps: a
    // gateway sending the old key would be handed back the payment the
    // customer already walked away from.
    expect($first)->not->toBe('');
    expect($order->getPaymentKey())->not->toBe($first);
});

it('keeps the public reference across a re-placement', function (): void {
    // The other side of the same coin, so the two are not confused: the
    // reference the customer was quoted is the one thing that must NOT move.
    [$cart] = makeCheckoutCart();

    $order = placeableDraft();

    $cart->add(new FakeBuyable('1', 2000));
    $cart->place($order);

    $reference = $order->order_id;

    $order->setPaymentStatus(PaymentStatus::Failed);
    $cart->place($order);

    expect($order->order_id)->toBe($reference);
});

it('records an attempt when a gateway starts one', function (): void {
    $order = new InMemoryOrder();
    $order->payment_key = 'key-1';

    $attempt = $order->startPaymentAttempt('tr_fake_1');

    // The order points at the live attempt, and the attempt carries what it
    // was made under.
    expect($order->getPaymentId())->toBe('tr_fake_1');
    expect($attempt->payment_id)->toBe('tr_fake_1');
    expect($attempt->payment_key)->toBe('key-1');
    expect($attempt->getStatus())->toBe(PaymentStatus::Pending);
    expect($attempt->isCurrent())->toBeTrue();
});

it('leaves the superseded attempt behind, still answerable', function (): void {
    $order = new InMemoryOrder();

    $dead = $order->startPaymentAttempt('tr_fake_1');
    $live = $order->startPaymentAttempt('tr_fake_2');

    expect($order->attempts)->toHaveCount(2);
    expect($dead->isCurrent())->toBeFalse();
    expect($live->isCurrent())->toBeTrue();
});

it('moves the order on news about the attempt it waits on', function (): void {
    $dispatcher = bootEcommerce();

    $gateway = new FakeGateway(new FakeRedirector());
    $gateway->reports['tr_fake_1'] = PaymentStatus::Paid;

    $order = new InMemoryOrder();
    $order->payment_status = PaymentStatus::Pending->value;
    $attempt = $order->startPaymentAttempt('tr_fake_1');

    $webhook = new InMemoryPaymentWebhook($gateway, $dispatcher);
    $webhook->attempts['tr_fake_1'] = $attempt;

    $webhook->handle('tr_fake_1');

    expect($attempt->getStatus())->toBe(PaymentStatus::Paid);
    expect($order->getPaymentStatus())->toBe(PaymentStatus::Paid);
});

it('records a dead attempt without moving the order', function (): void {
    $dispatcher = bootEcommerce();

    $gateway = new FakeGateway(new FakeRedirector());
    $gateway->reports['tr_fake_1'] = PaymentStatus::Expired;

    $order = new InMemoryOrder();
    $order->payment_status = PaymentStatus::Pending->value;

    $dead = $order->startPaymentAttempt('tr_fake_1');
    $order->startPaymentAttempt('tr_fake_2');

    $webhook = new InMemoryPaymentWebhook($gateway, $dispatcher);
    $webhook->attempts['tr_fake_1'] = $dead;

    // Answered rather than 404'd — that is the whole point — and recorded
    // against the attempt it was about. The order is waiting on tr_fake_2 and
    // must not be told its money expired.
    $webhook->handle('tr_fake_1');

    expect($dead->getStatus())->toBe(PaymentStatus::Expired);
    expect($order->getPaymentStatus())->toBe(PaymentStatus::Pending);
});

it('still serves a gateway that records no attempt', function (): void {
    // Back-compatible on purpose: a gateway that assigns `payment_id` itself
    // rather than calling startPaymentAttempt() keeps working, through the
    // order's own column. It just leaves no history behind.
    $dispatcher = bootEcommerce();

    $gateway = new FakeGateway(new FakeRedirector());
    $gateway->reports['tr_fake_1'] = PaymentStatus::Paid;

    $order = new InMemoryOrder();
    $order->payment_id = 'tr_fake_1';
    $order->payment_status = PaymentStatus::Pending->value;

    $webhook = new InMemoryPaymentWebhook($gateway, $dispatcher);
    $webhook->orders['tr_fake_1'] = $order;

    $webhook->handle('tr_fake_1');

    expect($order->getPaymentStatus())->toBe(PaymentStatus::Paid);
});

it('offers a way back to an unfinished payment', function (): void {
    // The contract that keeps the gateway swappable: without it a shop
    // wanting "continue where you left off" reaches past the interface into
    // the provider's SDK.
    $gateway = new FakeGateway(new FakeRedirector());

    expect($gateway->resumeUrl('tr_fake_1'))->toBe(
        'https://pay.example/checkout/tr_fake_1'
    );

    // And nothing to offer once the provider has settled it.
    $gateway->reports['tr_fake_1'] = PaymentStatus::Paid;

    expect($gateway->resumeUrl('tr_fake_1'))->toBeNull();
});

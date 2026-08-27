<?php

declare(strict_types=1);

/*
 * The place-step: an existing order — a draft a project filled in
 * progressively, or a placed-but-unpaid one — frozen from the cart.
 *
 * checkout() is now one call into place() with a fresh order, so everything
 * CheckoutTest pins down covers the shared body; what this file adds is what
 * only an *existing* order can show. A draft arrives with an id, identity
 * columns it wrote itself, and no lines; placing it must copy the cart onto
 * it without inventing a sibling row, without blanking what the draft already
 * knows, and without ever handing out a second reference. Re-placement is the
 * same call again after a failed payment — same order, lines replaced, the
 * Created event honestly re-fired — and a paid order refuses loudly, because
 * re-freezing it would rewrite what the money already arrived for.
 *
 * The orders keep to memory through the same seams as everywhere else
 * (InMemoryOrder), so all of it runs with no database.
 */

use Tests\Support\FakeBuyable;
use Tests\Support\InMemoryOrder;
use Tests\Support\UnsavedCustomer;
use Tnt\Ecommerce\AlreadyPaid;
use Tnt\Ecommerce\Events\Order\Created;
use Tnt\Ecommerce\Order\OrderState;
use Tnt\Ecommerce\Payment\PaymentStatus;

beforeEach(function (): void {
    // Same three lines as CheckoutTest: the Dispatcher facade wants a
    // container, and nothing else outside the seams does.
    $app = new Oak\Container\Container();

    Oak\Facade::setContainer($app);

    $app->singleton(
        Oak\Contracts\Dispatcher\DispatcherInterface::class,
        Oak\Dispatcher\Dispatcher::class
    );
});

/**
 * A draft as a project's progressive checkout form leaves it: saved (it has
 * an id), stamped draft, carrying the identity typed so far — and no lines.
 *
 * @return InMemoryOrder
 */
function draftInProgress(): InMemoryOrder
{
    $draft = new InMemoryOrder();
    $draft->created = time();
    $draft->updated = time();
    $draft->state = OrderState::Draft->value;
    $draft->first_name = 'Maria';
    $draft->last_name = 'Verstraeten';
    $draft->email = 'maria@example.be';
    $draft->save();

    return $draft;
}

it('places a draft: freezes the money and copies the lines', function (): void {
    [$cart, , $payment] = makeCheckoutCart();
    $draft = draftInProgress();

    $cart->add(new FakeBuyable('1', 2000), 2);

    $order = $cart->place($draft);

    // The same row, not a sibling: the whole point of the draft flow.
    expect($order)->toBe($draft);
    expect($draft->getState())->toBe(OrderState::Placed);
    expect($draft->subtotal)->toBe(4000);
    expect($draft->total)->toBe(4000);
    expect($draft->lines)->toHaveCount(1);
    expect($draft->getPaymentStatus())->toBe(PaymentStatus::Pending);

    // The reference is built on the id the draft already had, and pay() gets
    // the finished order, exactly as in a one-shot checkout.
    expect($draft->order_id)->toStartWith($draft->id . '-');
    expect($payment->paid)->toBe([$draft]);
});

it('does not blank the identity a draft carries', function (): void {
    // A guest draft wrote its identity progressively; placing with no
    // customer must freeze the money without touching who is buying.
    [$cart] = makeCheckoutCart();
    $draft = draftInProgress();

    $cart->add(new FakeBuyable('1', 2000));
    $cart->place($draft);

    expect($draft->getFirstName())->toBe('Maria');
    expect($draft->getLastName())->toBe('Verstraeten');
    expect($draft->getEmail())->toBe('maria@example.be');
    expect($draft->getCustomer())->toBeNull();
});

it('freezes the customer when the place-step is handed one', function (): void {
    // The account flow through place(): same freezing checkout() always did.
    [$cart] = makeCheckoutCart();
    $draft = draftInProgress();

    $customer = new UnsavedCustomer();
    $customer->first_name = 'An';
    $customer->last_name = 'Peeters';
    $customer->email = 'an@example.be';

    $cart->add(new FakeBuyable('1', 2000));
    $cart->place($draft, $customer);

    expect($draft->getCustomer())->toBe($customer);
    expect($draft->getFirstName())->toBe('An');
    expect($draft->getEmail())->toBe('an@example.be');
});

it('links the cart to the order it became', function (): void {
    [$cart, $storage] = makeCheckoutCart();
    $draft = draftInProgress();

    $cart->add(new FakeBuyable('1', 2000));

    expect($storage->getOrderId())->toBeNull();

    $cart->place($draft);

    // Written by the package at placement; a project may also write it
    // earlier, at draft creation — the placement write is idempotent then.
    expect($storage->getOrderId())->toBe((int) $draft->id);
});

it('announces placement, not draft birth', function (): void {
    // The draft existed for as long as the form did and nothing fired. The
    // one Created is the placement.
    [$cart] = makeCheckoutCart();
    $draft = draftInProgress();
    $announced = 0;

    Oak\Dispatcher\Facade\Dispatcher::addListener(
        Created::class,
        function () use (&$announced): void {
            $announced++;
        }
    );

    $cart->add(new FakeBuyable('1', 2000));

    expect($announced)->toBe(0);

    $cart->place($draft);

    expect($announced)->toBe(1);
});

it('re-places a failed order as the same order', function (): void {
    // The dry-mollie shape: place, the gateway reports failure, the customer
    // edits the basket and accepts again. Same row, same reference, lines
    // replaced rather than stacked.
    [$cart, , $payment] = makeCheckoutCart();
    $draft = draftInProgress();

    $cart->add(new FakeBuyable('1', 2000), 2);
    $cart->place($draft);

    $reference = $draft->order_id;
    $draft->setPaymentStatus(PaymentStatus::Failed);

    $cart->add(new FakeBuyable('2', 350));
    $cart->place($draft);

    expect($draft->order_id)->toBe($reference);
    expect($draft->getState())->toBe(OrderState::Placed);
    expect($draft->getPaymentStatus())->toBe(PaymentStatus::Pending);

    // Cleared once per placement with an id, so the second basket does not
    // stack on the first — two lines now, not three.
    expect($draft->clearCount)->toBe(2);
    expect($draft->lines)->toHaveCount(2);
    expect($draft->subtotal)->toBe(4350);

    // pay() ran both times; a re-placement is a fresh attempt to pay.
    expect($payment->paid)->toBe([$draft, $draft]);
});

it('re-fires Created on re-placement', function (): void {
    // Created means "this order was (re)placed", so listeners must be
    // idempotent per order id — documented in docs/orders.md, held here.
    [$cart] = makeCheckoutCart();
    $draft = draftInProgress();
    $announced = 0;

    Oak\Dispatcher\Facade\Dispatcher::addListener(
        Created::class,
        function () use (&$announced): void {
            $announced++;
        }
    );

    $cart->add(new FakeBuyable('1', 2000));
    $cart->place($draft);
    $draft->setPaymentStatus(PaymentStatus::Canceled);
    $cart->place($draft);

    expect($announced)->toBe(2);
});

it('re-places through every unpaid status', function (
    PaymentStatus $status
): void {
    // The legal set in one place: everything a gateway can report short of
    // money arriving leaves the order re-placeable.
    [$cart] = makeCheckoutCart();
    $draft = draftInProgress();

    $cart->add(new FakeBuyable('1', 2000));
    $cart->place($draft);
    $draft->setPaymentStatus($status);

    $cart->place($draft);

    expect($draft->getPaymentStatus())->toBe(PaymentStatus::Pending);
})->with([
    'pending' => [PaymentStatus::Pending],
    'failed' => [PaymentStatus::Failed],
    'canceled' => [PaymentStatus::Canceled],
    'expired' => [PaymentStatus::Expired],
]);

it('refuses to place a paid order', function (): void {
    [$cart] = makeCheckoutCart();
    $draft = draftInProgress();

    $cart->add(new FakeBuyable('1', 2000));
    $cart->place($draft);
    $draft->setPaymentStatus(PaymentStatus::Paid);

    $lines = $draft->lines;

    expect(fn() => $cart->place($draft))->toThrow(AlreadyPaid::class);

    // Refused before anything was touched: the paid order's lines stand.
    expect($draft->lines)->toBe($lines);
    expect($draft->getPaymentStatus())->toBe(PaymentStatus::Paid);
});

it('refuses a refunded order too', function (): void {
    // Not in the pending/failed/canceled/expired set on purpose: the money
    // history exists either way, and the same guard the webhook listeners
    // write through (canTransitionTo) refuses to leave refunded.
    [$cart] = makeCheckoutCart();
    $draft = draftInProgress();

    $cart->add(new FakeBuyable('1', 2000));
    $cart->place($draft);
    $draft->setPaymentStatus(PaymentStatus::Paid);
    $draft->setPaymentStatus(PaymentStatus::Refunded);

    expect(fn() => $cart->place($draft))->toThrow(AlreadyPaid::class);
});

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

use Tests\Support\ChildFirstCartStorage;
use Tests\Support\FakeBuyable;
use Tests\Support\FakePayment;
use Tests\Support\InMemoryOrder;
use Tests\Support\InMemoryOrderCart;
use Tests\Support\UnsavedCustomer;
use Tnt\Ecommerce\AlreadyPaid;
use Tnt\Ecommerce\Events\Order\Created;
use Tnt\Ecommerce\Account\GuestUserResolver;
use Tnt\Ecommerce\Fulfillment\InMemoryAttributeStorage;
use Tnt\Ecommerce\Order\OrderState;
use Tnt\Ecommerce\Payment\PaymentStatus;
use Tnt\Ecommerce\Shop\Shop;

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

it('re-places a fully refunded order', function (): void {
    // sc-11448: a refunded order is an order nobody has paid for. It used to
    // be refused here, which — paired with a gateway that maps any refund to
    // `refunded` — ended the order's life over a goodwill gesture.
    [$cart] = makeCheckoutCart();
    $draft = draftInProgress();

    $cart->add(new FakeBuyable('1', 2000));
    $cart->place($draft);
    $draft->setPaymentStatus(PaymentStatus::Paid);
    $draft->setPaymentStatus(PaymentStatus::Refunded);

    expect($cart->place($draft))->toBe($draft);
    expect($draft->getPaymentStatus())->toBe(PaymentStatus::Pending);
});

it('refuses a partially refunded order', function (): void {
    // The other half of sc-11448: most of the money is still here, so this is
    // an order somebody paid for, and re-freezing it would rewrite what they
    // paid for.
    [$cart] = makeCheckoutCart();
    $draft = draftInProgress();

    $cart->add(new FakeBuyable('1', 2000));
    $cart->place($draft);
    $draft->setPaymentStatus(PaymentStatus::Paid);
    $draft->setPaymentStatus(PaymentStatus::PartiallyRefunded);

    expect(fn() => $cart->place($draft))->toThrow(AlreadyPaid::class);
});

it('guards re-placement through isRePlaceable itself', function (): void {
    // sc-11258: one source of truth. The order's answer is forced against
    // what its columns say, and place() follows the ANSWER — so the guard
    // reads through Order::isRePlaceable() rather than re-deriving the rule,
    // and the two can never drift.
    [$cart] = makeCheckoutCart();
    $order = new Tests\Support\ForcedRePlaceabilityOrder();
    $order->created = time();
    $order->updated = time();

    $cart->add(new FakeBuyable('1', 2000));
    $cart->place($order);

    // Placed and pending — re-placeable on its columns — but the forced "no"
    // must refuse it all the same.
    $order->rePlaceable = false;

    expect(fn() => $cart->place($order))->toThrow(AlreadyPaid::class);

    // And the forced "yes" is followed just as blindly.
    $order->rePlaceable = true;
    $order->payment_status = PaymentStatus::Paid->value;

    expect($cart->place($order))->toBe($order);
});

it('places a draft isRePlaceable says no to', function (): void {
    // The distinction in one test: a draft is placeable but not RE-placeable
    // — it has no placement to repeat — so place() must accept it without
    // ever asking isRePlaceable().
    [$cart] = makeCheckoutCart();
    $draft = draftInProgress();

    expect($draft->isRePlaceable())->toBeFalse();

    $cart->add(new FakeBuyable('1', 2000));

    expect($cart->place($draft))->toBe($draft);
    expect($draft->getState())->toBe(OrderState::Placed);
    expect($draft->isRePlaceable())->toBeTrue();
});

it('freezes the parent link onto the order lines', function (): void {
    // sc-11448: a host that models mandatory accessories had to rebuild this
    // AFTER placement, from a Created listener, because the cart had nowhere
    // to hold it. Now placement copies it, and the listener disappears.
    [$cart] = makeCheckoutCart();
    $draft = draftInProgress();

    $crate = new FakeBuyable('crate', 1500);
    $deposit = new FakeBuyable('deposit', 300);

    $cart->add($crate);
    [$crateLine] = $cart->items();
    $cart->add($deposit, 1, [], $crateLine);

    $cart->place($draft);

    $frozenCrate = $draft->frozen[$crateLine->getId()];
    [, $depositCartLine] = $cart->items();
    $frozenDeposit = $draft->frozen[$depositCartLine->getId()];

    expect($frozenCrate->getParent())->toBeNull();
    expect($frozenDeposit->getParent())->toBe($frozenCrate);
});

it('links a child frozen before its parent', function (): void {
    // The reason the link is a second pass: this cart hands the deposit over
    // BEFORE the crate it hangs off, so a one-pass copy would have no parent
    // row to point at and the link would silently go missing.
    $storage = new ChildFirstCartStorage();
    $cart = new InMemoryOrderCart(
        new Shop(new InMemoryAttributeStorage()),
        $storage,
        new FakePayment(),
        new GuestUserResolver()
    );

    $draft = draftInProgress();

    $crate = new FakeBuyable('crate', 1500);
    $deposit = new FakeBuyable('deposit', 300);

    $cart->add($crate);
    [$crateLine] = $storage->items();
    $cart->add($deposit, 1, [], $crateLine);

    // Youngest first: the deposit is handed over before its crate.
    [$first] = $cart->items();

    expect($first->getParent())->not->toBeNull();

    $cart->place($draft);

    $depositLine = $draft->frozen[$first->getId()];
    $crateFrozen = $draft->frozen[$crateLine->getId()];

    expect($depositLine->getParent())->toBe($crateFrozen);
});

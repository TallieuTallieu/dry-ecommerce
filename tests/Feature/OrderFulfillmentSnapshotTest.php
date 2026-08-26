<?php

declare(strict_types=1);

/*
 * The order's own copy of the fulfillment attributes it was placed with.
 *
 * A delivery date and a timeslot are chosen at checkout and live in session
 * storage — which dies with the session, long before whoever fulfills the
 * order goes looking for them. The order recorded the method's id and its
 * cost, but never the answers. checkout() now freezes the method's *required*
 * attributes as JSON onto `ecommerce_order.fulfillment_attributes`, the same
 * move freezeCustomer() makes with the addresses and for the same reason: what
 * an order was placed with is a statement about the past, and only its own
 * copy can back one.
 *
 * Same machinery as OrderAddressSnapshotTest: the real Cart::checkout() runs
 * against an order that keeps to memory, so the freeze and the read-back are
 * the production code with no database anywhere near them.
 */

use Tests\Support\FakeBuyable;
use Tests\Support\FakeFulfillment;
use Tests\Support\InMemoryOrder;
use Tests\Support\UnsavedCustomer;
use Tnt\Ecommerce\Fulfillment\MissingAttribute;

beforeEach(function (): void {
    // checkout() dispatches Created through a facade, which wants a container.
    // Same three lines as CheckoutTest, and no connection between them.
    $app = new Oak\Container\Container();

    Oak\Facade::setContainer($app);

    $app->singleton(
        Oak\Contracts\Dispatcher\DispatcherInterface::class,
        Oak\Dispatcher\Dispatcher::class
    );
});

it('freezes the required attributes onto the order as JSON', function (): void {
    $fulfillment = new FakeFulfillment('pickup', 0, ['date', 'timeslot']);
    [$cart] = makeCheckoutCart([$fulfillment]);

    $fulfillment->setAttribute('date', '2026-08-28');
    $fulfillment->setAttribute('timeslot', 4);

    $cart->add(new FakeBuyable('1', 2000));
    $cart->setFulfillment($fulfillment);
    $cart->checkout(new UnsavedCustomer());

    $order = $cart->placed();

    // The exact column value, not just the decoded reading: this string is the
    // contract a shop's own queries run against when it counts pickups per
    // (date, timeslot), so its shape is worth pinning.
    expect($order->fulfillment_attributes)->toBe(
        '{"date":"2026-08-28","timeslot":4}'
    );

    expect($order->getFulfillmentAttribute('date'))->toBe('2026-08-28');
    expect($order->getFulfillmentAttribute('timeslot'))->toBe(4);
    expect($order->getFulfillmentAttributes())->toBe([
        'date' => '2026-08-28',
        'timeslot' => 4,
    ]);
});

it(
    'survives the attribute storage the session took with it',
    function (): void {
        $fulfillment = new FakeFulfillment('pickup', 0, ['date']);
        [$cart] = makeCheckoutCart([$fulfillment]);

        $fulfillment->setAttribute('date', '2026-08-28');

        $cart->add(new FakeBuyable('1', 2000));
        $cart->setFulfillment($fulfillment);
        $cart->checkout(new UnsavedCustomer());

        // The session ending is, from the order's point of view, the attribute
        // changing underneath it. The frozen copy must not notice.
        $fulfillment->setAttribute('date', '2026-09-01');

        expect($cart->placed()->getFulfillmentAttribute('date'))->toBe(
            '2026-08-28'
        );
    }
);

it('freezes only what the method requires', function (): void {
    // An optional attribute is a question the method could live without an
    // answer to, and the frozen copy is the list of answers it could not.
    // A shop that wants a note on the order requires it.
    $fulfillment = new FakeFulfillment('pickup', 0, ['date']);
    [$cart] = makeCheckoutCart([$fulfillment]);

    $fulfillment->setAttribute('date', '2026-08-28');
    $fulfillment->setAttribute('note', 'leave with neighbour');

    $cart->add(new FakeBuyable('1', 2000));
    $cart->setFulfillment($fulfillment);
    $cart->checkout(new UnsavedCustomer());

    expect($cart->placed()->getFulfillmentAttributes())->toBe([
        'date' => '2026-08-28',
    ]);
    expect($cart->placed()->getFulfillmentAttribute('note'))->toBeNull();
});

it('leaves the column null when no method was chosen', function (): void {
    [$cart] = makeCheckoutCart();

    $cart->add(new FakeBuyable('1', 2000));
    $cart->checkout(new UnsavedCustomer());

    expect($cart->placed()->fulfillment_attributes)->toBeNull();
    expect($cart->placed()->getFulfillmentAttributes())->toBe([]);
});

it('leaves the column null when the method asks nothing', function (): void {
    // Null rather than '{}': an empty object would claim questions were asked
    // and answered with nothing, which is not what happened.
    $fulfillment = new FakeFulfillment('post', 475);
    [$cart] = makeCheckoutCart([$fulfillment]);

    $cart->add(new FakeBuyable('1', 2000));
    $cart->setFulfillment($fulfillment);
    $cart->checkout(new UnsavedCustomer());

    expect($cart->placed()->fulfillment_attributes)->toBeNull();
});

it('refuses to freeze an answer that was never given', function (): void {
    // The same MissingAttribute the live method throws, let through on
    // purpose: an order frozen without the answer its method requires is that
    // absence made permanent, and the kitchen finds out on the day. A shop
    // asks validateAttributes() before checking out, exactly as before.
    $fulfillment = new FakeFulfillment('pickup', 0, ['date']);
    [$cart] = makeCheckoutCart([$fulfillment]);

    $cart->add(new FakeBuyable('1', 2000));
    $cart->setFulfillment($fulfillment);

    expect(fn() => $cart->checkout(new UnsavedCustomer()))->toThrow(
        MissingAttribute::class
    );
});

it('reads an order written before the column existed', function (): void {
    // A row from before this revision has no JSON to decode. It answers empty
    // rather than erroring, exactly like the frozen addresses on an order from
    // before sc-11172 answer blank.
    $order = new InMemoryOrder();

    expect($order->getFulfillmentAttributes())->toBe([]);
    expect($order->getFulfillmentAttribute('date'))->toBeNull();
});

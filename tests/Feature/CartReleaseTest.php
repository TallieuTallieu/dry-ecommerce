<?php

declare(strict_types=1);

/*
 * The cart's afterlife: Paid soft-deletes the basket behind the order.
 *
 * Cart::checkout() deliberately leaves the cart standing — with an
 * asynchronous gateway the visitor may come back from a failed payment and
 * must find their basket. The moment the basket is finally spent is Paid, so
 * the provider's listener follows the cart→order link and writes `deleted`
 * instead of deleting: the row keeps pointing at its order forever, which is
 * the provenance it survives for. Both storages then treat the row as absent
 * (CookieCartStorageTest holds the storage half; here the listener's half).
 *
 * Like the other listener tests, the honest way to reach the closure is to
 * boot the provider and dispatch a real Paid — with the CartRelease binding
 * replaced by the fake that answers its one query from memory.
 */

use Oak\Contracts\Dispatcher\DispatcherInterface;
use Oak\Dispatcher\Dispatcher;
use Tests\Support\FakeCartRelease;
use Tests\Support\InMemoryCart;
use Tests\Support\InMemoryOrder;
use Tests\Support\WebContainer;
use Tnt\Ecommerce\Cart\CartRelease;
use Tnt\Ecommerce\EcommerceServiceProvider;
use Tnt\Ecommerce\Events\Order\Paid;
use Tnt\Ecommerce\Events\Order\PaymentFailed;

/**
 * A booted provider whose CartRelease is the fake, handed back for reading.
 *
 * @return array{DispatcherInterface, FakeCartRelease}
 */
function bootWithCartRelease(): array
{
    $app = new WebContainer();

    $app->singleton(DispatcherInterface::class, Dispatcher::class);
    $app->singleton(CartRelease::class, FakeCartRelease::class);

    Oak\Facade::setContainer($app);

    (new EcommerceServiceProvider())->boot($app);

    /** @var DispatcherInterface $dispatcher */
    $dispatcher = $app->get(DispatcherInterface::class);

    /** @var FakeCartRelease $release */
    $release = $app->get(CartRelease::class);

    return [$dispatcher, $release];
}

it('soft-deletes the cart behind a paid order', function (): void {
    [$dispatcher, $release] = bootWithCartRelease();

    $cart = new InMemoryCart();
    $cart->created = time();
    $cart->save();
    $cart->order = 9;
    $release->cart = $cart;

    $order = new InMemoryOrder();
    $order->id = 9;

    $dispatcher->dispatch(Paid::class, new Paid($order));

    // Soft: a timestamp on the row, not a missing row. The order link stays.
    expect($cart->deleted)->toBeInt();
    expect($cart->hardDeleted)->toBeFalse();
    expect($cart->order)->toBe(9);
    expect($release->lookedUp)->toBe([9]);
});

it('leaves an already-released cart alone', function (): void {
    // Webhooks arrive at least once: the second Paid finds no living cart —
    // the notDeleted() scope — and must not move the first mark.
    [$dispatcher, $release] = bootWithCartRelease();

    $cart = new InMemoryCart();
    $cart->created = time();
    $cart->save();
    $cart->deleted = 12345;
    $release->cart = $cart;

    $order = new InMemoryOrder();
    $order->id = 9;

    $dispatcher->dispatch(Paid::class, new Paid($order));

    expect($cart->deleted)->toBe(12345);
    expect($cart->saveCount)->toBe(1);
});

it('releases nothing for an order no cart points at', function (): void {
    // Plain checkout() on a synchronous shop clears the cart itself; the
    // listener finding nothing is the ordinary case, not an error.
    [$dispatcher, $release] = bootWithCartRelease();

    $order = new InMemoryOrder();
    $order->id = 9;

    $dispatcher->dispatch(Paid::class, new Paid($order));

    expect($release->lookedUp)->toBe([9]);
});

it('does not look anything up for an unsaved order', function (): void {
    [$dispatcher, $release] = bootWithCartRelease();

    // Paid on an order with no id — nothing to join on, and the status
    // listener runs first and saves, so guard on the id the release step
    // sees, not on trust.
    $release->release(new InMemoryOrder());

    expect($release->lookedUp)->toBe([]);
});

it('keeps its hands off the cart for every other event', function (): void {
    // Only Paid spends the basket. A failed payment is exactly the moment
    // the cart must survive.
    [$dispatcher, $release] = bootWithCartRelease();

    $cart = new InMemoryCart();
    $cart->created = time();
    $cart->save();
    $release->cart = $cart;

    $order = new InMemoryOrder();
    $order->id = 9;

    $dispatcher->dispatch(PaymentFailed::class, new PaymentFailed($order));

    expect($cart->deleted)->toBeNull();
    expect($release->lookedUp)->toBe([]);
});

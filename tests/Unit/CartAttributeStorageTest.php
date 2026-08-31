<?php

declare(strict_types=1);

/*
 * Fulfillment attributes on the cart row instead of the session.
 *
 * The bag a fulfillment method collects — a date, a slot — used to live under
 * one session key and died with the session. CartAttributeStorage keeps it on
 * the visitor's cart row through whichever CartStorageInterface is bound, so
 * the answers live exactly as long as the basket they belong to. The freeze
 * path (Cart::checkout reading method->getAttribute()) is deliberately
 * untouched: the storage moved underneath it, which the last test proves.
 */

use Tests\Support\FakeBuyable;
use Tests\Support\FakeFulfillment;
use Tests\Support\FakePayment;
use Tests\Support\InMemoryOrderCart;
use Tnt\Ecommerce\Account\GuestUserResolver;
use Tnt\Ecommerce\Cart\InMemoryCartStorage;
use Tnt\Ecommerce\Fulfillment\CartAttributeStorage;
use Tnt\Ecommerce\Shop\Shop;

it('round-trips an attribute through the cart storage', function (): void {
    $carts = new InMemoryCartStorage();
    $storage = new CartAttributeStorage($carts);

    expect($storage->has('date'))->toBeFalse();
    expect($storage->get('date'))->toBeNull();

    $storage->set('date', '2026-08-28');

    expect($storage->has('date'))->toBeTrue();
    expect($storage->get('date'))->toBe('2026-08-28');
    expect($carts->getFulfillmentAttributes())->toBe([
        'date' => '2026-08-28',
    ]);
});

it('keeps the bag across method resolutions', function (): void {
    // Two CartAttributeStorage instances over one cart storage are the same
    // method resolved on two requests: what the first wrote, the second
    // reads, because the cart row — not the instance — holds the bag.
    $carts = new InMemoryCartStorage();

    $firstRequest = new CartAttributeStorage($carts);
    $firstRequest->set('date', '2026-08-28');
    $firstRequest->set('timeslot', 4);

    $secondRequest = new CartAttributeStorage($carts);

    expect($secondRequest->get('date'))->toBe('2026-08-28');
    expect($secondRequest->get('timeslot'))->toBe(4);
});

it('adds to the bag rather than replacing it', function (): void {
    $carts = new InMemoryCartStorage();
    $storage = new CartAttributeStorage($carts);

    $storage->set('date', '2026-08-28');
    $storage->set('timeslot', 4);

    expect($carts->getFulfillmentAttributes())->toBe([
        'date' => '2026-08-28',
        'timeslot' => 4,
    ]);
});

it('freezes cart-held attributes onto the order intact', function (): void {
    // The whole seam, end to end: the shop hands the method a cart-backed
    // storage, the method's answers land on the cart, and checkout() freezes
    // them onto the order through the same getAttribute() it always read.
    $app = new Oak\Container\Container();
    Oak\Facade::setContainer($app);
    $app->singleton(
        Oak\Contracts\Dispatcher\DispatcherInterface::class,
        Oak\Dispatcher\Dispatcher::class
    );

    $carts = new InMemoryCartStorage();
    $shop = new Shop(new CartAttributeStorage($carts));

    $method = new FakeFulfillment('pickup', 0, ['date', 'timeslot']);
    $shop->addFulfillment($method);

    $cart = new InMemoryOrderCart(
        $shop,
        $carts,
        new FakePayment(),
        new GuestUserResolver()
    );

    $cart->add(new FakeBuyable('1', 2000));
    $method->setAttribute('date', '2026-08-28');
    $method->setAttribute('timeslot', 4);
    $cart->setFulfillment($method);

    $cart->checkout();

    expect($cart->placed()->getFulfillmentAttributes())->toBe([
        'date' => '2026-08-28',
        'timeslot' => 4,
    ]);
});

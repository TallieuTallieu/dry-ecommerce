<?php

declare(strict_types=1);

/*
 * The nullable customer: a guest order has no customer row at all.
 *
 * Until sc-11260 every checkout had to bring a Customer, so a guest cost a
 * throwaway row whose only job was satisfying a NOT NULL column. Now null is
 * the guest: `ecommerce_order.customer` is nullable, getCustomer() answers
 * null, and the identity an order was placed under lives — as it always did —
 * on the order's own frozen columns. The account flow is byte-for-byte what
 * it was, which CheckoutTest still pins down; this file covers the new path.
 */

use Tests\Support\FakeBuyable;
use Tests\Support\InMemoryOrder;
use Tests\Support\UnsavedCustomer;
use Tnt\Ecommerce\Order\OrderState;

beforeEach(function (): void {
    $app = new Oak\Container\Container();

    Oak\Facade::setContainer($app);

    $app->singleton(
        Oak\Contracts\Dispatcher\DispatcherInterface::class,
        Oak\Dispatcher\Dispatcher::class
    );
});

it('checks out with no customer at all', function (): void {
    [$cart, , $payment] = makeCheckoutCart();

    $cart->add(new FakeBuyable('1', 2000), 2);

    $order = $cart->checkout();

    expect($order)->toBe($cart->placed());
    expect($cart->placed()->getCustomer())->toBeNull();
    expect($cart->placed()->customer)->toBeNull();

    // A guest order is still a whole order: money frozen, lines copied,
    // placed, paid for.
    expect($cart->placed()->total)->toBe(4000);
    expect($cart->placed()->lines)->toHaveCount(1);
    expect($cart->placed()->getState())->toBe(OrderState::Placed);
    expect($payment->paid)->toBe([$order]);
});

it('freezes nothing identity-wise for a guest', function (): void {
    // freezeCustomer(null) must not run: there is nobody to copy, and a
    // draft's own columns (empty here, filled in the draft flow) must stand.
    [$cart] = makeCheckoutCart();

    $cart->add(new FakeBuyable('1', 2000));
    $cart->checkout();

    expect($cart->placed()->getFirstName())->toBe('');
    expect($cart->placed()->getLastName())->toBe('');
    expect($cart->placed()->getEmail())->toBe('');
});

it('still freezes a customer when one checks out', function (): void {
    // The account flow through the same nullable signature.
    [$cart] = makeCheckoutCart();
    $customer = new UnsavedCustomer();
    $customer->first_name = 'An';
    $customer->last_name = 'Peeters';
    $customer->email = 'an@example.be';

    $cart->add(new FakeBuyable('1', 2000));
    $cart->checkout($customer);

    expect($cart->placed()->getCustomer())->toBe($customer);
    expect($cart->placed()->getFirstName())->toBe('An');
    expect($cart->placed()->getEmail())->toBe('an@example.be');
});

it('reads a legacy order as placed', function (): void {
    // Every order from before the state column was a real one — '' and words
    // this package does not know both read Placed, mirroring how
    // getPaymentStatus() reads legacy '' as the status that claims nothing.
    $legacy = new InMemoryOrder();

    expect($legacy->getState())->toBe(OrderState::Placed);

    $legacy->state = 'authorised';

    expect($legacy->getState())->toBe(OrderState::Placed);
});

it('reads a draft as a draft', function (): void {
    $draft = new InMemoryOrder();
    $draft->state = OrderState::Draft->value;

    expect($draft->getState())->toBe(OrderState::Draft);
});

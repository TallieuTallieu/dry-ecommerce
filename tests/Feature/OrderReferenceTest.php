<?php

declare(strict_types=1);

/*
 * A shop's own order_id, and the uuid every order carries beside it.
 *
 * The default reference is CheckoutTest's: those tests hand no reference in,
 * so they hold that a shop that configures nothing keeps `12-K4M7QX9RTB`.
 * These hold the seam — a configured class is what placement asks, once per
 * order — and the uuid a URL names the order by.
 */

use Tests\Support\UuidV4;
use Oak\Config\Repository;
use Oak\Contracts\Config\RepositoryInterface;
use Tests\Support\CountingOrderReference;
use Tests\Support\FakeBuyable;
use Tests\Support\InMemoryOrder;
use Tests\Support\InMemoryYearlyOrderReference;
use Tests\Support\UnsavedCustomer;
use Tests\Support\WebContainer;
use Tnt\Ecommerce\Contracts\OrderReferenceInterface;
use Tnt\Ecommerce\EcommerceServiceProvider;
use Tnt\Ecommerce\Order\OrderState;
use Tnt\Ecommerce\Order\RandomOrderReference;
use Tnt\Ecommerce\Order\YearlyOrderReference;
use Tnt\Ecommerce\Payment\PaymentStatus;

it('takes order_id from the reference it was handed', function (): void {
    $references = new CountingOrderReference();
    [$cart] = makeCheckoutCart(references: $references);

    $cart->add(new FakeBuyable('1', 2000));
    $cart->checkout(new UnsavedCustomer());

    expect($cart->placed()->order_id)->toBe('2600001');
});

it(
    'asks for a reference once, however often the order is placed',
    function (): void {
        // A re-placed order is the order the customer was already quoted: a
        // second number would be a second order on the bank statement.
        $references = new CountingOrderReference();
        [$cart, , , , $ledger] = makeCheckoutCart(references: $references);

        $draft = new InMemoryOrder();
        $draft->state = OrderState::Draft->value;

        $cart->add(new FakeBuyable('1', 2000));
        $cart->place($draft);
        reportOn($ledger, $draft, PaymentStatus::Failed);
        $cart->place($draft);

        expect($draft->order_id)->toBe('2600001');
        expect($references->asked)->toBe(1);
    }
);

/**
 * @param array<string, mixed> $ecommerce
 * @return object
 */
function referenceFor(array $ecommerce): object
{
    $app = new WebContainer();
    $app->instance(
        RepositoryInterface::class,
        new Repository(['ecommerce' => $ecommerce])
    );

    (new EcommerceServiceProvider())->register($app);

    return $app->get(OrderReferenceInterface::class);
}

it('binds the random reference when the shop names none', function (): void {
    expect(referenceFor([]))->toBeInstanceOf(RandomOrderReference::class);
    expect(referenceFor(['order_reference' => '']))->toBeInstanceOf(
        RandomOrderReference::class
    );
});

it('binds the reference class the shop names', function (): void {
    expect(
        referenceFor(['order_reference' => CountingOrderReference::class])
    )->toBeInstanceOf(CountingOrderReference::class);
});

it('gives a new order a version 4 uuid on its first save', function (): void {
    [$cart] = makeCheckoutCart();

    $cart->add(new FakeBuyable('1', 2000));
    $cart->checkout(new UnsavedCustomer());

    expect($cart->placed()->uuid)->toMatch(UuidV4::PATTERN);
});

it('gives a draft its uuid before placement', function (): void {
    // A draft is a row the visitor may come back to, so a URL can need it
    // before anything has been placed.
    $draft = new InMemoryOrder();
    $draft->state = OrderState::Draft->value;
    $draft->save();

    expect($draft->uuid)->toMatch(UuidV4::PATTERN);
});

it('keeps an order\'s uuid through every later save', function (): void {
    [$cart, , , , $ledger] = makeCheckoutCart();

    $draft = new InMemoryOrder();
    $draft->state = OrderState::Draft->value;
    $draft->save();
    $uuid = $draft->uuid;

    $cart->add(new FakeBuyable('1', 2000));
    $cart->place($draft);
    reportOn($ledger, $draft, PaymentStatus::Failed);
    $cart->place($draft);

    expect($draft->uuid)->toBe($uuid);
});

it('numbers orders by year, five digits after the year', function (): void {
    $references = new InMemoryYearlyOrderReference();
    $order = new InMemoryOrder();

    expect($references->reference($order))->toBe('2600001');
    expect($references->reference($order))->toBe('2600002');
});

it('starts counting again from 1 in a new year', function (): void {
    $references = new InMemoryYearlyOrderReference();
    $order = new InMemoryOrder();

    $references->reference($order);
    $references->reference($order);
    $references->currentYear = 2027;

    expect($references->reference($order))->toBe('2700001');
    expect($references->reference($order))->toBe('2700002');
});

it(
    'widens the counter past 99999 rather than stop a checkout',
    function (): void {
        // Longer, but never a number any other order already has.
        $references = new InMemoryYearlyOrderReference();
        $references->last[2026] = 99998;
        $order = new InMemoryOrder();

        expect($references->reference($order))->toBe('2699999');
        expect($references->reference($order))->toBe('26100000');
    }
);

it('pads a year in the first decade of a century', function (): void {
    $references = new InMemoryYearlyOrderReference();
    $references->currentYear = 2105;

    expect($references->reference(new InMemoryOrder()))->toBe('0500001');
});

it('places an order under its yearly number', function (): void {
    [$cart] = makeCheckoutCart(references: new InMemoryYearlyOrderReference());

    $cart->add(new FakeBuyable('1', 2000));
    $cart->checkout(new UnsavedCustomer());

    expect($cart->placed()->order_id)->toBe('2600001');
});

it('binds the yearly reference a shop opts into', function (): void {
    expect(
        referenceFor(['order_reference' => YearlyOrderReference::class])
    )->toBeInstanceOf(YearlyOrderReference::class);
});

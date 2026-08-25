<?php

declare(strict_types=1);

/*
 * How a shop's configuration becomes the tax it charges.
 *
 * TaxTest asks what the figures come to given a TaxPolicy. This file asks the
 * question underneath it: whether a shop that writes `ecommerce.prices` in its
 * config ever gets that policy at all. Every other test in the suite hands the
 * cart a policy by hand, so between them they would all still pass with the
 * binding deleted and every shop quietly back on inclusive pricing.
 *
 * It matters more here than coverage usually does, because the README calls
 * `ecommerce.prices` "the one thing you have to tell the package" and the whole
 * feature is inert if the answer does not arrive. The wiring it holds is also
 * subtler than it looks: Cart takes `?TaxPolicy $tax = null`, and a container
 * that checked for a default before checking its own bindings would inject null
 * without erroring. Oak checks its bindings first (Container::get()), so the
 * policy arrives -- and this file is what would notice if that ever stopped
 * being true.
 *
 * EcommerceServiceProvider::register() is what runs here, rather than boot()
 * as in CouponRedemptionTest. The two storages it binds reach for the session,
 * so they are rebound afterwards to the in-memory pair the package ships for
 * exactly this.
 */

use Oak\Config\Repository;
use Oak\Contracts\Config\RepositoryInterface;
use Oak\Contracts\Dispatcher\DispatcherInterface;
use Oak\Dispatcher\Dispatcher;
use Tests\Support\FakeTaxableBuyable;
use Tests\Support\PercentageTaxRate;
use Tests\Support\WebContainer;
use Tnt\Ecommerce\Cart\InMemoryCartStorage;
use Tnt\Ecommerce\Contracts\AttributeStorageInterface;
use Tnt\Ecommerce\Contracts\CartInterface;
use Tnt\Ecommerce\Contracts\CartStorageInterface;
use Tnt\Ecommerce\EcommerceServiceProvider;
use Tnt\Ecommerce\Fulfillment\InMemoryAttributeStorage;
use Tnt\Ecommerce\Tax\PriceConvention;
use Tnt\Ecommerce\Tax\TaxPolicy;

/**
 * A container registered against one shop's `ecommerce` configuration.
 *
 * @param array<string, mixed> $ecommerce What the shop put under `ecommerce`.
 * @return WebContainer
 */
function shopConfiguredWith(array $ecommerce): WebContainer
{
    $app = new WebContainer();

    // Bound before register(), which reads the repository while registering
    // rather than when something is first resolved out of it.
    $app->instance(
        RepositoryInterface::class,
        new Repository(['ecommerce' => $ecommerce])
    );

    (new EcommerceServiceProvider())->register($app);

    // After register(), so these replace the session-backed pair it binds.
    $app->singleton(CartStorageInterface::class, InMemoryCartStorage::class);
    $app->singleton(
        AttributeStorageInterface::class,
        InMemoryAttributeStorage::class
    );

    // The default payment takes one, and resolving a cart resolves a payment.
    // Nothing here dispatches anything; it just has to be constructible.
    $app->singleton(DispatcherInterface::class, Dispatcher::class);

    return $app;
}

/**
 * The tax policy a shop's configuration produced.
 *
 * @param array<string, mixed> $ecommerce
 * @return TaxPolicy
 */
function policyConfiguredWith(array $ecommerce): TaxPolicy
{
    /** @var TaxPolicy $policy */
    $policy = shopConfiguredWith($ecommerce)->get(TaxPolicy::class);

    return $policy;
}

it('reads the convention a shop configured', function (): void {
    expect(policyConfiguredWith(['prices' => 'exclusive'])->convention())->toBe(
        PriceConvention::Exclusive
    );
    expect(policyConfiguredWith(['prices' => 'inclusive'])->convention())->toBe(
        PriceConvention::Inclusive
    );
});

it('reads a shop that has said nothing as inclusive', function (): void {
    // The upgrade path. A shop that installs this version and changes no
    // config keeps the totals it had yesterday.
    expect(policyConfiguredWith([])->convention())->toBe(
        PriceConvention::Inclusive
    );
});

it('reads anything it does not recognise as inclusive', function (
    mixed $configured
): void {
    // Deliberately the quiet failure rather than the loud one, and this is the
    // direction that makes it safe: a typo costs a wrong tax figure, where
    // falling back to exclusive would add 21% to every total in the shop. Note
    // 'Exclusive' among these -- the backed values are lower case, and
    // tryFrom() is exact.
    expect(policyConfiguredWith(['prices' => $configured])->convention())->toBe(
        PriceConvention::Inclusive
    );
})->with([
    'a typo' => ['exclusve'],
    'the wrong case' => ['Exclusive'],
    'an empty string' => [''],
    'not a string at all' => [true],
    'an array' => [['exclusive']],
]);

it('taxes delivery at the rate a shop configured', function (): void {
    $policy = policyConfiguredWith([
        'prices' => 'exclusive',
        'delivery_tax_rate' => 21,
    ]);

    expect($policy->taxOnDelivery(475))->toBe(100);
});

it('reads anything that is not a number as a rate of 0', function (
    array $ecommerce
): void {
    // A rate has to arrive as a number to count. Anything else reads as 0
    // rather than being coerced, so a stray string in a config file cannot
    // start taxing delivery at a figure nobody chose.
    //
    // 0 and not null: the two would give the same cents at every rate and
    // under both conventions, and nothing in the package could tell a shop
    // which one it had. See EcommerceServiceProvider::configuredRate().
    expect(policyConfiguredWith($ecommerce)->taxOnDelivery(475))->toBe(0);
})->with([
    'unset' => [['prices' => 'exclusive']],
    'a number as a string' => [
        ['prices' => 'exclusive', 'delivery_tax_rate' => '21'],
    ],
    'null' => [['prices' => 'exclusive', 'delivery_tax_rate' => null]],
    'set to 0' => [['prices' => 'exclusive', 'delivery_tax_rate' => 0]],
]);

it('gives the configured policy to the cart it builds', function (): void {
    // The one that would catch the binding going missing. Everything above
    // tests the policy the container holds; this tests that the cart a shop
    // actually resolves is the one holding it.
    $app = shopConfiguredWith(['prices' => 'exclusive']);

    /** @var CartInterface $cart */
    $cart = $app->get(CartInterface::class);

    $cart->add(new FakeTaxableBuyable('1', 1250, new PercentageTaxRate(21)));

    expect($cart->getTax())->toBe(263);
    expect($cart->getTotal())->toBe(1513);
});

it('gives an unconfigured cart the totals it always had', function (): void {
    // The same cart in a shop that configured nothing: the tax is reported,
    // the total does not move, and 1250 is still 1250.
    $app = shopConfiguredWith([]);

    /** @var CartInterface $cart */
    $cart = $app->get(CartInterface::class);

    $cart->add(new FakeTaxableBuyable('1', 1250, new PercentageTaxRate(21)));

    expect($cart->getTax())->toBe(217);
    expect($cart->getTotal())->toBe(1250);
});

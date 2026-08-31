<?php

declare(strict_types=1);

/*
 * Which cart storage a shop's configuration buys it.
 *
 * TaxConfigurationTest's question, asked of `ecommerce.cart_lifetime`: every
 * other test hands a storage in by hand, so all of them would still pass with
 * the binding logic deleted. A shop that sets a lifetime gets the cookie cart
 * and the visitor keeps their basket across sessions; a shop that sets
 * nothing — or nonsense — keeps the session cart it has always had. The
 * attribute storage rides along: cart-backed by default now, whichever cart
 * storage is underneath.
 */

use Oak\Config\Repository;
use Oak\Contracts\Config\RepositoryInterface;
use Oak\Contracts\Cookie\CookieInterface;
use Oak\Session\Session;
use Tests\Support\FakeCookie;
use Tests\Support\WebContainer;
use Tnt\Ecommerce\Cart\CookieCartStorage;
use Tnt\Ecommerce\Cart\SessionCartStorage;
use Tnt\Ecommerce\Contracts\AttributeStorageInterface;
use Tnt\Ecommerce\Contracts\CartStorageInterface;
use Tnt\Ecommerce\EcommerceServiceProvider;
use Tnt\Ecommerce\Fulfillment\CartAttributeStorage;

/**
 * A container registered against one shop's `ecommerce` configuration, with
 * the two request-scoped services both storages might ask for bound as fakes.
 *
 * @param array<string, mixed> $ecommerce
 * @return WebContainer
 */
function shopWithCartConfig(array $ecommerce): WebContainer
{
    $app = new WebContainer();

    $app->instance(
        RepositoryInterface::class,
        new Repository(['ecommerce' => $ecommerce])
    );

    (new EcommerceServiceProvider())->register($app);

    $app->instance(CookieInterface::class, new FakeCookie());
    $app->instance(Session::class, new Session('test', new SessionHandler()));

    return $app;
}

it('binds the cookie cart when a lifetime is configured', function (): void {
    $storage = shopWithCartConfig(['cart_lifetime' => 30])->get(
        CartStorageInterface::class
    );

    expect($storage)->toBeInstanceOf(CookieCartStorage::class);
});

it('keeps the session cart when no lifetime is set', function (
    array $ecommerce
): void {
    // Unset, zero, negative or mistyped all read as "no lifetime" — the
    // fallback that changes nothing, same style as every other config key.
    $storage = shopWithCartConfig($ecommerce)->get(CartStorageInterface::class);

    expect($storage)->toBeInstanceOf(SessionCartStorage::class);
})->with([
    'unset' => [[]],
    'zero' => [['cart_lifetime' => 0]],
    'negative' => [['cart_lifetime' => -3]],
    'a number as a string' => [['cart_lifetime' => '30']],
]);

it('backs the attribute storage with the cart by default', function (): void {
    $app = shopWithCartConfig([]);

    expect($app->get(AttributeStorageInterface::class))->toBeInstanceOf(
        CartAttributeStorage::class
    );
});

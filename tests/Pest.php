<?php

use Tests\Support\FakePayment;
use Tests\Support\InMemoryOrderCart;
use Tnt\Ecommerce\Account\GuestUserResolver;
use Tnt\Ecommerce\Cart\Cart;
use Tnt\Ecommerce\Cart\InMemoryCartStorage;
use Tnt\Ecommerce\Contracts\FulfillmentInterface;
use Tnt\Ecommerce\Contracts\UserResolverInterface;
use Tnt\Ecommerce\Fulfillment\InMemoryAttributeStorage;
use Tnt\Ecommerce\Shop\Shop;
use Tnt\Ecommerce\Tax\TaxPolicy;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

uses(Tests\TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * A cart, the storage behind it and the shop it belongs to.
 *
 * Lives here rather than in one test file because more than one of them needs
 * it now — CartTest for the arithmetic, BuyableCapabilitiesTest for what the
 * cart asks a buyable. A global helper declared inside a test file is only
 * available to the files Pest happens to load after it, which is a load-order
 * dependency worth not having.
 *
 * The cart resolves accounts through a seam too, and it defaults here to the
 * same guest resolver the service provider binds when a shop has not configured
 * one — so every existing caller keeps building the cart of a shop with no
 * accounts, and a test about the account path passes one in.
 *
 * @param array<int, FulfillmentInterface> $fulfillments
 * @param UserResolverInterface|null $users Who is signed in; guest by default.
 * @param TaxPolicy|null $tax
 * @return array{0: Cart, 1: InMemoryCartStorage, 2: Shop}
 */
function makeCart(
    array $fulfillments = [],
    ?UserResolverInterface $users = null,
    ?TaxPolicy $tax = null
): array {
    $shop = new Shop(new InMemoryAttributeStorage());

    foreach ($fulfillments as $fulfillment) {
        $shop->addFulfillment($fulfillment);
    }

    $storage = new InMemoryCartStorage();
    $cart = new Cart(
        $shop,
        $storage,
        new FakePayment(),
        $users ?? new GuestUserResolver(),
        $tax
    );

    return [$cart, $storage, $shop];
}

/**
 * The same cart, but one that checks out into memory.
 *
 * {@see makeCart()} builds the real {@see Cart}, whose `checkout()` writes rows
 * and so cannot run here. This builds {@see InMemoryOrderCart}, which differs
 * from it by one overridden method and lets the body of `checkout()` run for
 * real against an order that keeps to memory.
 *
 * Hands back the payment as well as the storage, because what `checkout()` does
 * last — hand the finished order to the payment — is part of what there was no
 * way to check before.
 *
 * @param array<int, FulfillmentInterface> $fulfillments
 * @param UserResolverInterface|null $users
 * @param TaxPolicy|null $tax
 * @return array{InMemoryOrderCart, InMemoryCartStorage, FakePayment, Shop}
 */
function makeCheckoutCart(
    array $fulfillments = [],
    ?UserResolverInterface $users = null,
    ?TaxPolicy $tax = null
): array {
    $shop = new Shop(new InMemoryAttributeStorage());

    foreach ($fulfillments as $fulfillment) {
        $shop->addFulfillment($fulfillment);
    }

    $storage = new InMemoryCartStorage();
    $payment = new FakePayment();
    $cart = new InMemoryOrderCart(
        $shop,
        $storage,
        $payment,
        $users ?? new GuestUserResolver(),
        $tax
    );

    return [$cart, $storage, $payment, $shop];
}

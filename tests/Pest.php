<?php

use Tests\Support\FakePayment;
use Tnt\Ecommerce\Cart\Cart;
use Tnt\Ecommerce\Cart\InMemoryCartStorage;
use Tnt\Ecommerce\Contracts\FulfillmentInterface;
use Tnt\Ecommerce\Fulfillment\InMemoryAttributeStorage;
use Tnt\Ecommerce\Shop\Shop;

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
 * @param array<int, FulfillmentInterface> $fulfillments
 * @return array{0: Cart, 1: InMemoryCartStorage, 2: Shop}
 */
function makeCart(array $fulfillments = []): array
{
    $shop = new Shop(new InMemoryAttributeStorage());

    foreach ($fulfillments as $fulfillment) {
        $shop->addFulfillment($fulfillment);
    }

    $storage = new InMemoryCartStorage();
    $cart = new Cart($shop, $storage, new FakePayment());

    return [$cart, $storage, $shop];
}

<?php

namespace Tnt\Ecommerce\Facade;

use Oak\Facade;
use Tnt\Ecommerce\Contracts\FulfillmentInterface;
use Tnt\Ecommerce\Contracts\ShopInterface;

/**
 * Static access to the shop's fulfillment register.
 *
 * @method static mixed addFulfillment(FulfillmentInterface $fulfillment)
 * @method static FulfillmentInterface getFulfillment(string|int $id)
 * @method static bool hasFulfillment(string|int $id)
 * @method static array<string|int, FulfillmentInterface> getFulfillments()
 *
 * @extends Facade<ShopInterface>
 */
class Shop extends Facade
{
    /**
     * @return class-string<ShopInterface>
     */
    protected static function getContract(): string
    {
        return ShopInterface::class;
    }
}

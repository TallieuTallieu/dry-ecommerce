<?php

namespace Tnt\Ecommerce\Facade;

use Closure;
use Oak\Facade;
use Tnt\Ecommerce\Contracts\BuyableInterface;
use Tnt\Ecommerce\Contracts\CartInterface;
use Tnt\Ecommerce\Contracts\CartItemInterface;
use Tnt\Ecommerce\Contracts\CustomerInterface;
use Tnt\Ecommerce\Contracts\FulfillmentInterface;
use Tnt\Ecommerce\Contracts\OrderInterface;
use Tnt\Ecommerce\Model\DiscountCode;

/**
 * Static access to the visitor's cart.
 *
 * @method static mixed add(BuyableInterface $buyable, int $quantity = 1)
 * @method static mixed remove(BuyableInterface $buyable)
 * @method static array<int, CartItemInterface> items()
 * @method static mixed clear()
 * @method static mixed setFulfillment(FulfillmentInterface $fulfillment)
 * @method static FulfillmentInterface|null getFulfillment()
 * @method static float getFulfillmentCost()
 * @method static mixed addDiscount(DiscountCode $discount)
 * @method static DiscountCode|null getDiscount()
 * @method static OrderInterface checkout(CustomerInterface $customer, (Closure(OrderInterface): void)|null $callback = null)
 * @method static float getSubTotal()
 * @method static float getTotal()
 * @method static float getReduction()
 *
 * @extends Facade<CartInterface>
 */
class Cart extends Facade
{
    /**
     * @return class-string<CartInterface>
     */
    protected static function getContract(): string
    {
        return CartInterface::class;
    }
}

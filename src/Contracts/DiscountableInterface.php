<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Contracts;

/**
 * A buyable that may say it takes no discount — a deposit, a gift card. A
 * buyable that does not implement this takes the discount. A line that opts
 * out is not reached by any coupon, and does not count toward a coupon's
 * minimum. See docs/discounts.md#what-a-coupon-reaches.
 */
interface DiscountableInterface extends BuyableInterface
{
    /**
     * @return bool
     */
    public function takesDiscount(): bool;
}

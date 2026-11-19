<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Contracts;

/**
 * A {@see DiscountableInterface} whose answer depends on the coupon asking —
 * a deposit a voucher may pay but a percentage may not. Asked whenever the
 * coupon is known: its reduction, its minimum, the cart's split.
 * {@see DiscountableInterface::takesDiscount()} stays the answer where no
 * coupon is. See docs/discounts.md#what-a-coupon-reaches.
 */
interface DiscountableByCouponInterface extends DiscountableInterface
{
    /**
     * @param CouponInterface $coupon
     * @return bool
     */
    public function takesDiscountFrom(CouponInterface $coupon): bool;
}

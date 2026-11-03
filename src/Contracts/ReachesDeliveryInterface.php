<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Contracts;

/**
 * A {@see CouponInterface} whose reduction may come off delivery too. The
 * cart clamps every coupon to what it may reach: the lines that take a
 * discount, plus delivery only for a coupon answering true here. See
 * docs/discounts.md#what-a-coupon-reaches.
 */
interface ReachesDeliveryInterface
{
    /**
     * @return bool
     */
    public function reachesDelivery(): bool;
}

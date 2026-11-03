<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Contracts;

use Tnt\Ecommerce\Discount\CouponRefusal;

/**
 * A {@see CouponInterface} that can say why it does not apply, not only that
 * it does not. Optional; {@see CartInterface::applyCode()} is the call a
 * controller makes. See docs/discounts.md#telling-the-customer-why.
 */
interface RefusesCouponInterface
{
    /**
     * Why the coupon does not apply to this cart, or null when it does.
     *
     * @param TotalingInterface $totalingItem
     * @return CouponRefusal|null
     */
    public function refusal(TotalingInterface $totalingItem): ?CouponRefusal;
}

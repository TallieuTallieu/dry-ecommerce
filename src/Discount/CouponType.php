<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Discount;

/**
 * What a {@see \Tnt\Ecommerce\Model\Coupon} takes off. A percentage reaches
 * the lines that take a discount and never delivery; a fixed amount reaches
 * those lines and delivery, until its balance runs out. See docs/discounts.md.
 */
enum CouponType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
}

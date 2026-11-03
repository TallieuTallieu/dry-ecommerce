<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Discount;

use Tnt\Ecommerce\Contracts\RefusesCouponInterface;
use Tnt\Ecommerce\Contracts\TotalingInterface;
use Tnt\Ecommerce\Model\DiscountCode;

/**
 * Why a code does not apply — what a shop tells the customer who typed it.
 * The cases are this package's words, not a customer's: a shop translates
 * them. {@see RefusedCode} carries one with what its message needs. See
 * docs/discounts.md#telling-the-customer-why.
 */
enum CouponRefusal: string
{
    /**
     * Nothing was typed — only {@see \Tnt\Ecommerce\Contracts\CartInterface::applyCode()}
     * says it; {@see self::of()} is handed a lookup's result, never a string.
     */
    case Missing = 'missing';

    /** No such code, no coupon behind it, or a coupon that will not say. */
    case Unknown = 'unknown';
    case NotStarted = 'not_started';
    case Expired = 'expired';

    /** It has been used as often as it may be. */
    case UsedUp = 'used_up';

    /** A fixed coupon with nothing left on it. */
    case Empty = 'empty';

    /** What it may reach comes to less than its minimum. */
    case BelowMinimum = 'below_minimum';

    /**
     * Why this code does not apply to this cart, or null when it does. A
     * coupon that does not implement {@see RefusesCouponInterface} only says
     * yes or no, so its no reads as {@see self::Unknown}.
     *
     * @param DiscountCode|null $code What {@see \Tnt\Ecommerce\Repository\DiscountCodeRepository::byCode()}
     *                                found, or null.
     * @param TotalingInterface $totaling
     * @return self|null
     */
    public static function of(
        ?DiscountCode $code,
        TotalingInterface $totaling
    ): ?self {
        // One place loads the coupon and decides: RefusedCode::of().
        return RefusedCode::of($code, $totaling)?->reason;
    }
}

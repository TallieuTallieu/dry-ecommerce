<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Discount;

use Tnt\Ecommerce\Contracts\RefusesCouponInterface;
use Tnt\Ecommerce\Contracts\TotalingInterface;
use Tnt\Ecommerce\Model\Coupon;
use Tnt\Ecommerce\Model\DiscountCode;

/**
 * A typed code the cart would not take: why, and what the message for that
 * needs — so a shop words it without looking the coupon up again. What
 * {@see \Tnt\Ecommerce\Contracts\CartInterface::applyCode()} answers. See
 * docs/discounts.md#telling-the-customer-why.
 */
final readonly class RefusedCode
{
    /**
     * Public, so a shop's own cart can answer one too.
     *
     * @param CouponRefusal $reason
     * @param int|null $starts Unix time the coupon starts applying — set
     *                         only for {@see CouponRefusal::NotStarted}.
     * @param int|null $minimum Cents the lines that take a discount must
     *                          come to — set only for
     *                          {@see CouponRefusal::BelowMinimum}.
     */
    public function __construct(
        public CouponRefusal $reason,
        public ?int $starts = null,
        public ?int $minimum = null
    ) {}

    /**
     * Why this code does not apply to this cart, or null when it does, with
     * the start and the minimum filled in. A coupon that does not implement
     * {@see RefusesCouponInterface} only says yes or no, so its no reads as
     * {@see CouponRefusal::Unknown}. Only the shipped {@see Coupon} has a
     * start and a minimum to give; a shop's own coupon's refusal carries
     * neither. {@see CouponRefusal::of()} answers its reason alone.
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
        // One load: dry has no identity map, so each read is a fresh SELECT,
        // and the start or minimum must come from the coupon that refused.
        $coupon = $code?->coupon;

        if ($coupon === null) {
            return new self(CouponRefusal::Unknown);
        }

        $reason =
            $coupon instanceof RefusesCouponInterface
                ? $coupon->refusal($totaling)
                : null;

        if ($reason === null) {
            return $coupon->isRedeemable($totaling)
                ? null
                : new self(CouponRefusal::Unknown);
        }

        if (!($coupon instanceof Coupon)) {
            return new self($reason);
        }

        $late = $reason === CouponRefusal::NotStarted;
        $short = $reason === CouponRefusal::BelowMinimum;

        return new self(
            $reason,
            $late ? $coupon->getStarts() : null,
            $short ? $coupon->getMinimum() : null
        );
    }
}

<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Discount;

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
     * Why this code does not apply to this cart, or null when it does — as
     * {@see CouponRefusal::of()}, with the start and the minimum filled in.
     * Only the shipped {@see Coupon} has those to give; a shop's own
     * coupon's refusal carries neither.
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
        $reason = CouponRefusal::of($code, $totaling);

        if ($reason === null) {
            return null;
        }

        $coupon = $code?->coupon;

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

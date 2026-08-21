<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Contracts\CouponInterface;
use Tnt\Ecommerce\Contracts\TotalingInterface;
use Tnt\Ecommerce\Model\Order;
use Tnt\Ecommerce\Money;

/**
 * A coupon that takes a percentage off the subtotal.
 *
 * The other half of the rounding rule in practice: the rate applies to the
 * subtotal, so it is rounded once, there — not per line, and not again on the
 * total.
 */
final class PercentageCoupon implements CouponInterface
{
    public int $redeemCount = 0;

    /**
     * @param int|float $percentage The rate, as a percentage: 10 means 10% off.
     */
    public function __construct(private readonly int|float $percentage) {}

    public function isRedeemable(TotalingInterface $totalingItem): bool
    {
        return true;
    }

    public function getReduction(TotalingInterface $totalingItem): int
    {
        return Money::percentageOf(
            $totalingItem->getSubTotal(),
            $this->percentage
        );
    }

    /**
     * @param Order $order
     * @return void
     */
    public function redeem(Order $order)
    {
        $this->redeemCount++;
    }
}

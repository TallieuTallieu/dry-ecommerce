<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Contracts\CouponInterface;
use Tnt\Ecommerce\Contracts\TotalingInterface;
use Tnt\Ecommerce\Model\Order;

/**
 * A coupon that takes a fixed amount off, and can be told to stop being
 * redeemable.
 */
final class FakeCoupon implements CouponInterface
{
    public int $redeemCount = 0;

    public function __construct(
        private readonly float $reduction,
        private bool $redeemable = true
    ) {}

    public function stopBeingRedeemable(): void
    {
        $this->redeemable = false;
    }

    public function isRedeemable(TotalingInterface $totalingItem): bool
    {
        return $this->redeemable;
    }

    public function getReduction(TotalingInterface $totalingItem): float
    {
        return $this->reduction;
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

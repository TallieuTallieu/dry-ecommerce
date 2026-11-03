<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Contracts\CouponInterface;
use Tnt\Ecommerce\Contracts\ReachesDeliveryInterface;
use Tnt\Ecommerce\Contracts\TotalingInterface;
use Tnt\Ecommerce\Model\Order;

/**
 * A shop's own coupon that says whether it reaches delivery, and takes a
 * fixed number of cents off.
 */
final class FakeDeliveryCoupon implements
    CouponInterface,
    ReachesDeliveryInterface
{
    /**
     * @param int $reduction The amount off, in cents.
     * @param bool $reaches What reachesDelivery() answers.
     */
    public function __construct(
        private readonly int $reduction,
        private readonly bool $reaches = true
    ) {}

    public function isRedeemable(TotalingInterface $totalingItem): bool
    {
        return true;
    }

    public function getReduction(TotalingInterface $totalingItem): int
    {
        return $this->reduction;
    }

    public function reachesDelivery(): bool
    {
        return $this->reaches;
    }

    /**
     * @param Order $order
     * @return void
     */
    public function redeem(Order $order) {}
}

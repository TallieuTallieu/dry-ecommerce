<?php

namespace Tnt\Ecommerce\Contracts;

use Tnt\Ecommerce\Model\Order;

/**
 * Interface CouponInterface
 * @package Tnt\Ecommerce\Contracts
 */
interface CouponInterface
{
    /**
     * @param TotalingInterface $totalingItem
     * @return bool
     */
    public function isRedeemable(TotalingInterface $totalingItem): bool;

    /**
     * How much this coupon takes off, in cents.
     *
     * A coupon expressed as a percentage rounds with
     * {@see \Tnt\Ecommerce\Money::percentageOf()}, once, on the amount the
     * percentage applies to.
     *
     * @see \Tnt\Ecommerce\Money
     * @param TotalingInterface $totalingItem
     * @return int
     */
    public function getReduction(TotalingInterface $totalingItem): int;

    /**
     * @param Order $order
     * @return mixed
     */
    public function redeem(Order $order);
}

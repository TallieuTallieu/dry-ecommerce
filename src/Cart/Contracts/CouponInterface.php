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
     * @param TotalingInterface $totalingItem
     * @return float
     */
    public function getReduction(TotalingInterface $totalingItem): float;

    /**
     * @param Order $order
     * @return mixed
     */
    public function redeem(Order $order);
}
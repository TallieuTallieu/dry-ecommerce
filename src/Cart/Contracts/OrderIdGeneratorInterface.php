<?php

namespace Tnt\Ecommerce\Contracts;

use Tnt\Ecommerce\Model\Order;

/**
 * Interface OrderIdGeneratorInterface
 * @package Tnt\Ecommerce\Contracts
 */
interface OrderIdGeneratorInterface
{
    /**
     * @param Order $order
     * @return string
     */
    public function generate(Order $order): string;
}
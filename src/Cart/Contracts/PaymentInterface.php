<?php

namespace Tnt\Ecommerce\Contracts;

/**
 * Interface PaymentInterface
 * @package app\ecommerce
 */
interface PaymentInterface
{
    /**
     * @param OrderInterface $order
     * @return mixed
     */
    public function pay(OrderInterface $order);
}
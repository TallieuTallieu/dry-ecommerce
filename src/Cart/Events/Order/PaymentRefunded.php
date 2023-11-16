<?php

namespace Tnt\Ecommerce\Events\Order;

use Tnt\Ecommerce\Contracts\OrderInterface;

class PaymentRefunded extends OrderEvent
{
    private $refunds;

    /**
     * PaymentRefunded constructor.
     * @param OrderInterface $order
     * @param $refunds
     */
    public function __construct(OrderInterface $order, $refunds)
    {
        $this->refunds = $refunds;
        parent::__construct($order);
    }

    /**
     * @return mixed
     */
    public function getRefunds()
    {
        return $this->refunds;
    }
}
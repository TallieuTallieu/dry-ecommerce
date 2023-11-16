<?php

namespace Tnt\Ecommerce\Events\Order;

use Oak\Dispatcher\Event;
use Tnt\Ecommerce\Contracts\OrderInterface;

abstract class OrderEvent extends Event
{
    /**
     * @var OrderInterface $user
     */
    private $order;

    /**
     * OrderEvent constructor.
     * @param OrderInterface $order
     */
    public function __construct(OrderInterface $order)
    {
        $this->order = $order;
    }

    /**
     * @return OrderInterface
     */
    public function getOrder(): OrderInterface
    {
        return $this->order;
    }
}
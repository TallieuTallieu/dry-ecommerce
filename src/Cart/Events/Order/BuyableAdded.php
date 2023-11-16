<?php

namespace Tnt\Ecommerce\Events\Order;

use Tnt\Ecommerce\Contracts\BuyableInterface;
use Tnt\Ecommerce\Contracts\OrderInterface;

class BuyableAdded extends OrderEvent
{
    /**
     * @var BuyableInterface
     */
    private $buyable;

    /**
     * BuyableAdded constructor.
     * @param OrderInterface $order
     * @param BuyableInterface $buyable
     */
    public function __construct(OrderInterface $order, BuyableInterface $buyable)
    {
        $this->buyable = $buyable;

        parent::__construct($order);
    }

    /**
     * @return BuyableInterface
     */
    public function getBuyable(): BuyableInterface
    {
        return $this->buyable;
    }
}
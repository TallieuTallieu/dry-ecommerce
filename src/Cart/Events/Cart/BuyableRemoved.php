<?php

namespace Tnt\Ecommerce\Events\Cart;

use Tnt\Ecommerce\Contracts\BuyableInterface;
use Tnt\Ecommerce\Contracts\CartInterface;

class BuyableRemoved extends CartEvent
{
    /**
     * @var BuyableInterface
     */
    private $buyable;

    /**
     * BuyableRemoved constructor.
     * @param CartInterface $cart
     * @param BuyableInterface $buyable
     */
    public function __construct(CartInterface $cart, BuyableInterface $buyable)
    {
        $this->buyable = $buyable;

        parent::__construct($cart);
    }

    /**
     * @return BuyableInterface
     */
    public function getBuyable(): BuyableInterface
    {
        return $this->buyable;
    }
}
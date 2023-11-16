<?php

namespace Tnt\Ecommerce\Events\Cart;

use Oak\Dispatcher\Event;
use Tnt\Ecommerce\Contracts\CartInterface;

abstract class CartEvent extends Event
{
    /**
     * @var CartInterface $cart
     */
    private $cart;

    /**
     * OrderEvent constructor.
     * @param CartInterface $cart
     */
    public function __construct(CartInterface $cart)
    {
        $this->cart = $cart;
    }

    /**
     * @return CartInterface
     */
    public function getCart(): CartInterface
    {
        return $this->cart;
    }
}
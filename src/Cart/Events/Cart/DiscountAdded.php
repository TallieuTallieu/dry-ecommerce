<?php

namespace Tnt\Ecommerce\Events\Cart;

use Tnt\Ecommerce\Contracts\CartInterface;
use Tnt\Ecommerce\Model\DiscountCode;

class DiscountAdded extends CartEvent
{
    /**
     * @var DiscountCode $discount
     */
    private $discount;

    /**
     * DiscountAdded constructor.
     * @param CartInterface $cart
     * @param DiscountCode $discount
     */
    public function __construct(CartInterface $cart, DiscountCode $discount)
    {
        $this->discount = $discount;

        parent::__construct($cart);
    }

    /**
     * @return DiscountCode
     */
    public function getDiscount(): DiscountCode
    {
        return $this->discount;
    }
}
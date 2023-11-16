<?php

namespace Tnt\Ecommerce\Cart;

use Tnt\Ecommerce\Contracts\CartFactoryInterface;
use Tnt\Ecommerce\Contracts\StorableInterface;
use Tnt\Ecommerce\Model\Cart;

class CartFactory implements CartFactoryInterface
{
    public function create(): StorableInterface
    {
        $cart = new Cart();
        $cart->created = time();
        $cart->updated = time();
        $cart->save();

        return $cart;
    }
}
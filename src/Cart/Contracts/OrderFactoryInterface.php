<?php

namespace Tnt\Ecommerce\Contracts;

use Tnt\Ecommerce\Model\Order;

interface OrderFactoryInterface
{
    public function create(CartInterface $cart, CustomerInterface $customer): Order;
}
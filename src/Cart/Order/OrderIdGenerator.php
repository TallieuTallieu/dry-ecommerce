<?php

namespace Tnt\Ecommerce\Order;

use Tnt\Ecommerce\Contracts\OrderIdGeneratorInterface;
use Tnt\Ecommerce\Model\Order;

class OrderIdGenerator implements OrderIdGeneratorInterface
{
    public function generate(Order $order): string
    {
        // Generate an order id
        $start = rand(5, 8);
        $rest = 8 - $start;

        return $order->id.'-'.\dry\util\string\random($start).'_'.\dry\util\string\random($rest);
    }
}
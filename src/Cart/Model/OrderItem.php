<?php

namespace Tnt\Ecommerce\Model;

use dry\orm\Model;
use Tnt\Ecommerce\Contracts\BuyableInterface;
use Tnt\Ecommerce\Contracts\OrderItemInterface;

class OrderItem extends Model implements OrderItemInterface
{
    const TABLE = 'ecommerce_order_item';

    public static $special_fields = [
        'order' => Order::class,
    ];

    /**
     * @return int
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * @return BuyableInterface
     */
    public function getBuyable(): BuyableInterface
    {
        $item_class = $this->item_class;

        return $item_class::load($this->item_id);
    }
}
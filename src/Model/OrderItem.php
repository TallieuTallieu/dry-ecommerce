<?php

namespace Tnt\Ecommerce\Model;

use dry\orm\Model;
use Tnt\Ecommerce\Contracts\BuyableInterface;
use Tnt\Ecommerce\Contracts\OrderItemInterface;

/**
 * One line of a placed order, as stored in `ecommerce_order_item`.
 *
 * The price is the frozen line total from the cart, not the buyable's current
 * price.
 *
 * @property int|null $id
 * @property int $created
 * @property int $updated
 * @property Order|null $order
 * @property int $item_id
 * @property string $item_class
 * @property float $price
 * @property int $quantity
 */
class OrderItem extends Model implements OrderItemInterface
{
    const TABLE = 'ecommerce_order_item';

    /**
     * @var array<string, string>
     */
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
        /** @var class-string<Model&BuyableInterface> $item_class */
        $item_class = $this->item_class;

        return $item_class::load($this->item_id);
    }
}

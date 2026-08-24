<?php

namespace Tnt\Ecommerce\Model;

use dry\orm\Model;
use Tnt\Ecommerce\Contracts\BuyableInterface;
use Tnt\Ecommerce\Contracts\OrderItemInterface;

/**
 * One line of a placed order, as stored in `ecommerce_order_item`.
 *
 * The price is the frozen line total from the cart, in cents, not the
 * buyable's current price.
 *
 * @property int|null $id
 * @property int $created
 * @property int $updated
 * @property Order|null $order
 * @property int $item_id
 * @property string $item_class
 * @property int $price
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
     * The buyable this line points at, once something has asked for it.
     *
     * @see getBuyable()
     */
    private ?BuyableInterface $buyable = null;

    /**
     * @return int
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * The buyable this line points at, loaded once, as on
     * {@see CartItem::getBuyable()}.
     *
     * Less is riding on it here — the price is frozen on the row, so nothing on
     * this class needs the buyable to answer a question about the order. It is
     * a template asking twice that pays, and it pays the same `SELECT` each
     * time without this.
     *
     * @return BuyableInterface
     */
    public function getBuyable(): BuyableInterface
    {
        if ($this->buyable !== null) {
            return $this->buyable;
        }

        /** @var class-string<Model&BuyableInterface> $item_class */
        $item_class = $this->item_class;

        return $this->buyable = $item_class::load($this->item_id);
    }
}

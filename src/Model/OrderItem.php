<?php

namespace Tnt\Ecommerce\Model;

use dry\orm\Model;
use Tnt\Ecommerce\Cart\LineOptions;
use Tnt\Ecommerce\Contracts\BuyableInterface;
use Tnt\Ecommerce\Contracts\OrderItemInterface;

/**
 * One line of a placed order, as stored in `ecommerce_order_item`.
 *
 * The price is the frozen line total from the cart, in cents, not the
 * buyable's current price. The options are frozen the same way: the canonical
 * JSON the cart line was keyed on, copied at checkout by
 * {@see Order::add()}, or NULL for a line placed without any.
 *
 * @property int|null $id
 * @property int $created
 * @property int $updated
 * @property Order|null $order
 * @property int $item_id
 * @property string $item_class
 * @property int $price
 * @property int $quantity
 * @property string|null $options
 * @property OrderItem|null $parent
 */
class OrderItem extends Model implements OrderItemInterface
{
    const TABLE = 'ecommerce_order_item';

    /**
     * @var array<string, string>
     */
    public static $special_fields = [
        'order' => Order::class,
        'parent' => self::class,
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
     * The line total frozen at checkout, in cents — repricing the product
     * does not restate this line.
     *
     * @return int
     */
    public function getPrice(): int
    {
        return $this->price;
    }

    /**
     * The choices this line was placed with — the order's own frozen copy;
     * [] for a NULL column.
     *
     * @return array<array-key, mixed>
     */
    public function getOptions(): array
    {
        return LineOptions::decode($this->options);
    }

    /**
     * The line this one hangs off, as the cart had it at checkout, or null.
     *
     * @return OrderItemInterface|null
     */
    public function getParent(): ?OrderItemInterface
    {
        return $this->parent;
    }

    /**
     * Point this line at its parent. Deliberately not on
     * {@see OrderItemInterface}, which is a read-only view of a frozen line:
     * the only caller is {@see Order}, copying the cart's link at placement
     * once every line has a row.
     *
     * @param OrderItem|null $parent
     * @return void
     */
    public function setParent(?OrderItem $parent): void
    {
        $this->parent = $parent;
        $this->save();
    }

    /**
     * The buyable this line points at, loaded once and memoised, as on
     * {@see CartItem::getBuyable()}.
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

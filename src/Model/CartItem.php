<?php

namespace Tnt\Ecommerce\Model;

use dry\orm\Model;
use Tnt\Ecommerce\Contracts\BuyableInterface;
use Tnt\Ecommerce\Contracts\CartItemInterface;

/**
 * One line of a cart, as stored in `ecommerce_cart_item`.
 *
 * The buyable is referenced by class name plus foreign id rather than by a real
 * foreign key, because a buyable can be any model the project cares to make
 * sellable.
 *
 * @property int|null $id
 * @property int $created
 * @property int $updated
 * @property Cart|null $cart
 * @property int $item_id
 * @property string $item_class
 * @property int $quantity
 */
class CartItem extends Model implements CartItemInterface
{
    const TABLE = 'ecommerce_cart_item';

    /**
     * @var array<string, string>
     */
    public static $special_fields = [
        'cart' => Cart::class,
    ];

    /**
     * @return string
     */
    public function getId(): string
    {
        return (string) $this->id;
    }

    /**
     * @param BuyableInterface $buyable
     * @return mixed|void
     */
    public function setBuyable(BuyableInterface $buyable)
    {
        $this->item_class = get_class($buyable);
        $this->item_id = (int) $buyable->getId();
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

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->getBuyable()->getTitle();
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->getBuyable()->getDescription();
    }

    /**
     * The line total, in cents.
     *
     * @return int
     */
    public function getPrice(): int
    {
        return $this->getBuyable()->getPrice() * $this->getQuantity();
    }

    /**
     * @return int
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * @param int $quantity
     * @return mixed|void
     */
    public function setQuantity(int $quantity)
    {
        $this->quantity = $quantity;
        $this->updated = time();
        $this->save();
    }
}

<?php

namespace Tnt\Ecommerce\Model;

use dry\orm\Model;
use Tnt\Ecommerce\Contracts\BuyableInterface;
use Tnt\Ecommerce\Contracts\CartItemInterface;
use Tnt\Ecommerce\Money;

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
     * The buyable this line points at, once something has asked for it.
     *
     * A real property rather than a column: `dry\orm\Model`'s `__get()` and
     * `__set()` only fire for properties it cannot see, and they key off
     * `ecommerce_cart_item.<name>` in the row data, which has no `buyable`.
     *
     * @see getBuyable()
     */
    private ?BuyableInterface $buyable = null;

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
        $this->buyable = $buyable;
    }

    /**
     * The buyable this line points at, loaded once.
     *
     * `load()` is a plain `SELECT` with no identity map behind it, and almost
     * everything else on this class goes through here — {@see getTitle()},
     * {@see getDescription()} and {@see getPrice()} all need the buyable, as do
     * {@see \Tnt\Ecommerce\Cart\Cart::getSubTotal()} and
     * {@see \Tnt\Ecommerce\Cart\Cart::getTax()}. Without the memo a cart
     * template printing a title, a description and a price costs three queries
     * per line before the cart has totalled anything.
     *
     * It holds for the life of this object, which is one request. That matches
     * {@see \Tnt\Ecommerce\Cart\InMemoryCartItem}, which has held its buyable as
     * an instance all along — so the two implementations of
     * {@see \Tnt\Ecommerce\Contracts\CartItemInterface} now answer with the same
     * lifetime rather than one of them re-reading and the other not.
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
        return Money::lineTotal(
            $this->getBuyable()->getPrice(),
            $this->getQuantity()
        );
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

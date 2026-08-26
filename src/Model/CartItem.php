<?php

namespace Tnt\Ecommerce\Model;

use dry\orm\Model;
use Tnt\Ecommerce\Cart\LineOptions;
use Tnt\Ecommerce\Contracts\BuyableInterface;
use Tnt\Ecommerce\Contracts\CartItemInterface;
use Tnt\Ecommerce\Money;

/**
 * One line of a cart, as stored in `ecommerce_cart_item`. The buyable is
 * referenced by class name plus id; `options` holds the line's choices as
 * {@see LineOptions} canonical JSON (NULL for none) and is part of the merge
 * key.
 *
 * @property int|null $id
 * @property int $created
 * @property int $updated
 * @property Cart|null $cart
 * @property int $item_id
 * @property string $item_class
 * @property int $quantity
 * @property string|null $options
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
     * The buyable this line points at, loaded once and memoised for the life
     * of this object — without the memo every title/description/price read
     * costs a query.
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

    /**
     * The choices this line was added with, decoded off the row; [] for a
     * NULL column.
     *
     * @return array<array-key, mixed>
     */
    public function getOptions(): array
    {
        return LineOptions::decode($this->options);
    }
}

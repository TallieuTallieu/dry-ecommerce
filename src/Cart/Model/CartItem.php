<?php

namespace Tnt\Ecommerce\Model;

use dry\orm\Model;
use Tnt\Ecommerce\Contracts\BuyableInterface;
use Tnt\Ecommerce\Contracts\CartItemInterface;

class CartItem extends Model implements CartItemInterface
{
    const TABLE = 'ecommerce_cart_item';

    public static $special_fields = [
        'cart' => Cart::class,
    ];

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param BuyableInterface $buyable
     * @return mixed|void
     */
    public function setBuyable(BuyableInterface $buyable)
    {
        $this->item_class = get_class($buyable);
        $this->item_id = $buyable->getId();
    }

    /**
     * @return BuyableInterface
     */
    public function getBuyable(): BuyableInterface
    {
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
     * @return float
     */
    public function getPrice(): float
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
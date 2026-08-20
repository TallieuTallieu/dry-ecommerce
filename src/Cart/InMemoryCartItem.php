<?php

namespace Tnt\Ecommerce\Cart;

use Tnt\Ecommerce\Contracts\BuyableInterface;
use Tnt\Ecommerce\Contracts\CartItemInterface;

/**
 * A cart line that exists only in memory.
 *
 * The counterpart to {@see \Tnt\Ecommerce\Model\CartItem}, which is a database
 * row. This one holds the buyable itself instead of a class name and a foreign
 * id, so nothing about it needs a connection.
 *
 * @see InMemoryCartStorage
 */
class InMemoryCartItem implements CartItemInterface
{
    private BuyableInterface $buyable;

    private int $quantity;

    /**
     * @param BuyableInterface $buyable
     * @param int $quantity
     */
    public function __construct(BuyableInterface $buyable, int $quantity = 1)
    {
        $this->buyable = $buyable;
        $this->quantity = $quantity;
    }

    /**
     * There is no row to take an id from, so the line is identified the same
     * way the database identifies it: by which buyable it points at.
     *
     * @return string
     */
    public function getId(): string
    {
        return get_class($this->buyable) . ':' . $this->buyable->getId();
    }

    /**
     * @param BuyableInterface $buyable
     * @return void
     */
    public function setBuyable(BuyableInterface $buyable)
    {
        $this->buyable = $buyable;
    }

    /**
     * @return BuyableInterface
     */
    public function getBuyable(): BuyableInterface
    {
        return $this->buyable;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->buyable->getTitle();
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->buyable->getDescription();
    }

    /**
     * The line total, not the unit price — the same thing
     * {@see \Tnt\Ecommerce\Model\CartItem::getPrice()} returns.
     *
     * @return float
     */
    public function getPrice(): float
    {
        return $this->buyable->getPrice() * $this->quantity;
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
     * @return void
     */
    public function setQuantity(int $quantity)
    {
        $this->quantity = $quantity;
    }
}

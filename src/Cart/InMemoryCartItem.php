<?php

namespace Tnt\Ecommerce\Cart;

use Tnt\Ecommerce\Contracts\BuyableInterface;
use Tnt\Ecommerce\Contracts\CartItemInterface;
use Tnt\Ecommerce\Money;

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
     * @param string $id The line's id, issued by the storage that made it.
     * @param BuyableInterface $buyable
     * @param int $quantity
     * @param array<array-key, mixed> $options The choices the line was added
     *                                         with; [] when there were none.
     */
    public function __construct(
        private readonly string $id,
        BuyableInterface $buyable,
        int $quantity = 1,
        private readonly array $options = []
    ) {
        $this->buyable = $buyable;
        $this->quantity = $quantity;
    }

    /**
     * The id the storage issued when the line was made — issued, not derived
     * from the buyable, because one buyable can sit on several lines.
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
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
     * The line total in cents, not the unit price.
     *
     * @return int
     */
    public function getPrice(): int
    {
        return Money::lineTotal($this->buyable->getPrice(), $this->quantity);
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

    /**
     * The choices this line was added with, as they were handed over.
     *
     * @return array<array-key, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}

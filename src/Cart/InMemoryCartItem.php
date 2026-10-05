<?php

namespace Tnt\Ecommerce\Cart;

use Tnt\Ecommerce\Contracts\BuyableInterface;
use Tnt\Ecommerce\Contracts\CartItemInterface;
use Tnt\Ecommerce\Contracts\VariantInterface;
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
     * @var array<array-key, mixed>
     */
    private readonly array $options;

    /**
     * The line this one hangs off, or null.
     */
    private ?CartItemInterface $parent = null;

    /**
     * @param string $id The line's id, issued by the storage that made it.
     * @param BuyableInterface $buyable
     * @param int $quantity
     * @param array<array-key, mixed> $options The choices the line was added
     *                                         with; [] when there were none.
     * @param string|null $variant The id of the variant the line holds.
     */
    public function __construct(
        private readonly string $id,
        BuyableInterface $buyable,
        int $quantity = 1,
        array $options = [],
        private readonly ?string $variant = null
    ) {
        $this->buyable = $buyable;
        $this->quantity = $quantity;

        // Through the same canonicalisation the database storage applies, so
        // the two storages are indistinguishable through the contract — keys
        // read back sorted here too, exactly as docs/options.md promises.
        $this->options = LineOptions::decode(LineOptions::canonical($options));
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
        return Money::lineTotal(
            Variants::unitPrice($this->buyable, $this->getVariant()),
            $this->quantity
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
     * @return void
     */
    public function setQuantity(int $quantity)
    {
        $this->quantity = $quantity;
    }

    /**
     * The choices this line was added with, in canonical form.
     *
     * @return array<array-key, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @return VariantInterface|null
     */
    public function getVariant(): ?VariantInterface
    {
        return Variants::of($this->buyable, $this->variant);
    }

    /**
     * @return string|null
     */
    public function getVariantId(): ?string
    {
        return $this->variant;
    }

    /**
     * @return CartItemInterface|null
     */
    public function getParent(): ?CartItemInterface
    {
        return $this->parent;
    }

    /**
     * @param CartItemInterface|null $parent
     * @return void
     */
    public function setParent(?CartItemInterface $parent): void
    {
        $this->parent = $parent;
    }
}

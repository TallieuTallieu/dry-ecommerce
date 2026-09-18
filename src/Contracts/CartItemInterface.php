<?php

namespace Tnt\Ecommerce\Contracts;

/**
 * Interface CartItemInterface
 * @package Tnt\Ecommerce\Contracts
 */
interface CartItemInterface
{
    /**
     * @return string
     */
    public function getId(): string;

    /**
     * @param BuyableInterface $buyable
     * @return mixed
     */
    public function setBuyable(BuyableInterface $buyable);

    /**
     * @return BuyableInterface
     */
    public function getBuyable(): BuyableInterface;

    /**
     * @return string
     */
    public function getTitle(): string;

    /**
     * @return string
     */
    public function getDescription(): string;

    /**
     * The line total — quantity times unit price — in cents.
     *
     * @see \Tnt\Ecommerce\Money
     * @return int
     */
    public function getPrice(): int;

    /**
     * @return int
     */
    public function getQuantity(): int;

    /**
     * @param int $quantity
     * @return mixed
     */
    public function setQuantity(int $quantity);

    /**
     * The choices this line was added with, decoded, or [] when there were
     * none.
     *
     * @return array<array-key, mixed>
     */
    public function getOptions(): array;

    /**
     * The line this one hangs off — a deposit under its crate — or null for a
     * line that stands on its own. Part of the merge key: the same buyable
     * under two different parents is two lines. See docs/cart.md.
     *
     * @return CartItemInterface|null
     */
    public function getParent(): ?CartItemInterface;

    /**
     * @param CartItemInterface|null $parent
     * @return void
     */
    public function setParent(?CartItemInterface $parent): void;
}

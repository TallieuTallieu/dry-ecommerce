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
}

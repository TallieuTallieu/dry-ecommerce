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
     * @return float
     */
    public function getPrice(): float;

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
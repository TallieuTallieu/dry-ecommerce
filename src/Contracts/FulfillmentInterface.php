<?php

namespace Tnt\Ecommerce\Contracts;

/**
 * Interface FulfillmentInterface
 * @package Tnt\Ecommerce\Contracts
 */
interface FulfillmentInterface
{
    /**
     * The id the shop registers this method under, and the cart and order
     * store. Both tables hold it as a string, but integer ids are common.
     *
     * @return string|int
     */
    public function getId();

    /**
     * @param CartInterface $cart
     * @return float
     */
    public function getCost(CartInterface $cart): float;

    /**
     * @return string
     */
    public function getTitle(): string;

    /**
     * @param string $name
     * @return mixed
     */
    public function getAttribute(string $name);

    /**
     * @param string $name
     * @param mixed $value
     * @return mixed
     */
    public function setAttribute(string $name, $value);

    /**
     * @param string $name
     * @return bool
     */
    public function hasAttribute(string $name): bool;

    /**
     * @return bool
     */
    public function validateAttributes(): bool;

    /**
     * The names of the attributes that must be set before this method can be
     * used.
     *
     * @return array<int, string>
     */
    public function requireAttributes(): array;
}

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
     * What this method costs for the given cart, in cents.
     *
     * @see \Tnt\Ecommerce\Money
     * @param CartInterface $cart
     * @return int
     */
    public function getCost(CartInterface $cart): int;

    /**
     * @return string
     */
    public function getTitle(): string;

    /**
     * The guarded read: a required-but-unset attribute throws
     * {@see \Tnt\Ecommerce\Fulfillment\MissingAttribute}; any other unset one
     * answers null. For a mere prefill peek, use {@see attributeOr()}.
     *
     * @param string $name
     * @return mixed
     */
    public function getAttribute(string $name);

    /**
     * The peek: the attribute's value when set, $default otherwise — never a
     * throw, required or not. A separate method rather than a default
     * parameter on {@see getAttribute()}, because an explicit null default is
     * indistinguishable from no argument there.
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function attributeOr(string $name, mixed $default): mixed;

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

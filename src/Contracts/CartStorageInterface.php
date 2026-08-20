<?php

namespace Tnt\Ecommerce\Contracts;

use Tnt\Ecommerce\Model\DiscountCode;

/**
 * Holds the cart the current visitor is working in.
 *
 * Everything the cart needs in order to answer a question about itself — its
 * lines, its fulfillment choice, its discount — comes from here, and nothing
 * else. That is deliberate: it is the single boundary between the cart's
 * arithmetic and the session plus database the arithmetic used to be welded to.
 * Swap in an implementation that keeps its state in an array and the whole cart
 * runs in a unit test.
 *
 * The shipped default is session-backed
 * ({@see \Tnt\Ecommerce\Cart\SessionCartStorage}): the session says which
 * `ecommerce_cart` row belongs to this visitor, and the row holds the rest.
 */
interface CartStorageInterface
{
    /**
     * The lines currently in the cart.
     *
     * @return array<int, CartItemInterface>
     */
    public function items(): array;

    /**
     * Put a buyable in the cart, merging into an existing line for the same
     * buyable rather than adding a second one.
     *
     * @param BuyableInterface $buyable
     * @param int $quantity
     * @return void
     */
    public function add(BuyableInterface $buyable, int $quantity = 1): void;

    /**
     * Take a buyable out of the cart entirely, whatever its quantity.
     *
     * @param BuyableInterface $buyable
     * @return void
     */
    public function remove(BuyableInterface $buyable): void;

    /**
     * Discard the cart. The next read starts a fresh, empty one.
     *
     * @return void
     */
    public function clear(): void;

    /**
     * The id of the chosen fulfillment method, or null when none is chosen.
     *
     * Ids are whatever {@see FulfillmentInterface::getId()} returns; the cart
     * table stores them as a string, and integer ids are common, so both are
     * accepted here rather than forcing implementations to cast.
     *
     * @return string|int|null
     */
    public function getFulfillmentId(): string|int|null;

    /**
     * @param string|int|null $id
     * @return void
     */
    public function setFulfillmentId(string|int|null $id): void;

    /**
     * The discount code applied to the cart, if any.
     *
     * Whether that code is still redeemable is the cart's judgement, not the
     * storage's — storage only remembers what was applied.
     *
     * @return DiscountCode|null
     */
    public function getDiscount(): ?DiscountCode;

    /**
     * @param DiscountCode|null $discount
     * @return void
     */
    public function setDiscount(?DiscountCode $discount): void;
}

<?php

namespace Tnt\Ecommerce\Contracts;

use Tnt\Ecommerce\Model\DiscountCode;

/**
 * Holds the cart the current visitor is working in — the single boundary
 * between the cart's arithmetic and the session plus database. The shipped
 * default is {@see \Tnt\Ecommerce\Cart\SessionCartStorage}.
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
     * buyable *with the same options* — compared in canonical form
     * ({@see \Tnt\Ecommerce\Cart\LineOptions}).
     *
     * @param BuyableInterface $buyable
     * @param int $quantity
     * @param array<array-key, mixed> $options
     * @return void
     */
    public function add(
        BuyableInterface $buyable,
        int $quantity = 1,
        array $options = []
    ): void;

    /**
     * How many of one buyable the cart holds, summed across every
     * option-variant of it, or 0 — this feeds {@see CartInterface::canAdd()},
     * and stock counts the buyable, not the selection.
     *
     * @param BuyableInterface $buyable
     * @return int
     */
    public function quantityOf(BuyableInterface $buyable): int;

    /**
     * Take a buyable out of the cart entirely — every option-variant of it.
     * One line is what {@see removeItem()} is for.
     *
     * @param BuyableInterface $buyable
     * @return void
     */
    public function remove(BuyableInterface $buyable): void;

    /**
     * Set one line to a quantity, by the line's own id. Zero or less removes
     * the line; an id this cart does not hold is a no-op.
     *
     * @param string $itemId
     * @param int $quantity
     * @return void
     */
    public function updateQuantity(string $itemId, int $quantity): void;

    /**
     * Take one line out of the cart, by the line's own id. An unknown id is a
     * no-op.
     *
     * @param string $itemId
     * @return void
     */
    public function removeItem(string $itemId): void;

    /**
     * Discard the cart. The next read starts a fresh, empty one.
     *
     * @return void
     */
    public function clear(): void;

    /**
     * The id of the chosen fulfillment method, or null when none is chosen.
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
     * The discount code applied to the cart, if any — whether it is still
     * redeemable is the cart's judgement, not the storage's.
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

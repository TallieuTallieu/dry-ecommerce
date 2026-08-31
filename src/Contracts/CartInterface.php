<?php

namespace Tnt\Ecommerce\Contracts;

use Tnt\Ecommerce\Model\DiscountCode;
use Tnt\Ecommerce\Model\Order;

/**
 * Interface CartInterface
 * @package Tnt\Ecommerce\Contracts
 */
interface CartInterface
{
    /**
     * Put a buyable in the cart. A line is `(buyable, options)`, so the same
     * selection merges and a different one is a second line; stock does not
     * veto — see {@see canAdd()}.
     *
     * @param BuyableInterface $buyable
     * @param int $quantity
     * @param array<array-key, mixed> $options Part of the line's identity;
     *                                         copied onto the order line at
     *                                         checkout.
     * @return mixed
     */
    public function add(
        BuyableInterface $buyable,
        int $quantity = 1,
        array $options = []
    );

    /**
     * Whether the stock would cover the total the cart would then hold of this
     * buyable. Always true without {@see HasStockInterface}. Reported, not
     * enforced — {@see add()} adds either way.
     *
     * @param BuyableInterface $buyable
     * @param int $quantity
     * @return bool
     */
    public function canAdd(BuyableInterface $buyable, int $quantity = 1): bool;

    /**
     * Take a buyable out of the cart — every line of it. A caller that means
     * one variant says so by id, with {@see removeItem()}.
     *
     * @param BuyableInterface $buyable
     * @return mixed
     */
    public function remove(BuyableInterface $buyable);

    /**
     * Set one line to a quantity, by the line's own id
     * ({@see CartItemInterface::getId()}). Zero or less removes the line; an
     * unknown id is a no-op, not an error.
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
     * @return array<int, CartItemInterface>
     */
    public function items(): array;

    /**
     * @return mixed
     */
    public function clear();

    /**
     * @param FulfillmentInterface $fulfillment
     * @return mixed
     */
    public function setFulfillment(FulfillmentInterface $fulfillment);

    /**
     * @return null|FulfillmentInterface
     */
    public function getFulfillment(): ?FulfillmentInterface;

    /**
     * The cost of the chosen fulfillment method, in cents, or 0 when none is
     * chosen.
     *
     * @see \Tnt\Ecommerce\Money
     * @return int
     */
    public function getFulfillmentCost(): int;

    /**
     * @param DiscountCode $discount
     * @return mixed
     */
    public function addDiscount(DiscountCode $discount);

    /**
     * @return null|DiscountCode
     */
    public function getDiscount(): ?DiscountCode;

    /**
     * Turn the cart into a placed order in one go. Null customer is a guest
     * order — no customer row, identity frozen from nothing. See
     * docs/orders.md.
     *
     * @param CustomerInterface|null $customer
     * @return OrderInterface
     */
    public function checkout(
        ?CustomerInterface $customer = null
    ): OrderInterface;

    /**
     * Place an existing order — a draft, or a placed-but-unpaid one being
     * re-placed — from this cart. A paid order throws
     * {@see \Tnt\Ecommerce\AlreadyPaid}. See docs/orders.md.
     *
     * @param Order $order
     * @param CustomerInterface|null $customer Freezes identity when given;
     *                                         null leaves the draft's own
     *                                         columns standing.
     * @return OrderInterface
     */
    public function place(
        Order $order,
        ?CustomerInterface $customer = null
    ): OrderInterface;

    /**
     * The sum of the line totals, in cents.
     *
     * @see \Tnt\Ecommerce\Money
     * @return int
     */
    public function getSubTotal(): int;

    /**
     * Subtotal plus fulfillment cost minus reduction, in cents.
     *
     * @see \Tnt\Ecommerce\Money
     * @return int
     */
    public function getTotal(): int;

    /**
     * What the coupon in force takes off, in cents, or 0 when there is none.
     *
     * @see \Tnt\Ecommerce\Money
     * @return int
     */
    public function getReduction(): int;

    /**
     * The tax on the lines whose buyable implements {@see TaxableInterface},
     * in cents, rounded per line on what is left after the reduction. Whether
     * it is contained in {@see getTotal()} or added to it is the shop's
     * {@see \Tnt\Ecommerce\Tax\PriceConvention}. See docs/tax.md.
     *
     * @see \Tnt\Ecommerce\Money
     * @return int
     */
    public function getTax(): int;
}

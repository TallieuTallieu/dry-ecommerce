<?php

namespace Tnt\Ecommerce\Contracts;

use Tnt\Ecommerce\Model\DiscountCode;

/**
 * Interface CartInterface
 * @package Tnt\Ecommerce\Contracts
 */
interface CartInterface
{
    /**
     * Put a buyable in the cart, merging into the line it already has.
     *
     * Adds what it is asked to add. Stock does not veto it — see
     * {@see canAdd()}, which reports and leaves the decision to the shop.
     *
     * @param BuyableInterface $buyable
     * @param int $quantity
     * @return mixed
     */
    public function add(BuyableInterface $buyable, int $quantity = 1);

    /**
     * Whether the stock would cover this buyable in this quantity.
     *
     * True for anything that does not implement {@see HasStockInterface}: a
     * buyable with no stock behind it has no limit to run into. For one that
     * does, the quantity checked is the total the cart would hold afterwards,
     * not the addition alone.
     *
     * Reported, not enforced: {@see add()} adds either way, and what to do with
     * a false is the shop's call. {@see \Tnt\Ecommerce\Cart\Cart} says why.
     *
     * @param BuyableInterface $buyable
     * @param int $quantity
     * @return bool
     */
    public function canAdd(BuyableInterface $buyable, int $quantity = 1): bool;

    /**
     * @param BuyableInterface $buyable
     * @return mixed
     */
    public function remove(BuyableInterface $buyable);

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
     * @param CustomerInterface $customer
     * @return OrderInterface
     */
    public function checkout(CustomerInterface $customer): OrderInterface;

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
     * The tax on the lines whose buyable implements {@see TaxableInterface}, in
     * cents, rounded per line. 0 when none of them does.
     *
     * Each line is taxed on its full line total. {@see getReduction()} does not
     * enter this, because a coupon comes off the cart rather than off any line
     * in particular.
     *
     * Reported, not charged: this does not enter {@see getTotal()} and is not
     * written to the order. See {@see TaxableInterface} for why not.
     *
     * @see \Tnt\Ecommerce\Money
     * @return int
     */
    public function getTax(): int;
}

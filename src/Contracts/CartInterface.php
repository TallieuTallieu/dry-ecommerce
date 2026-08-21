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
     * @param BuyableInterface $buyable
     * @param int $quantity
     * @return mixed
     */
    public function add(BuyableInterface $buyable, int $quantity = 1);

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
}

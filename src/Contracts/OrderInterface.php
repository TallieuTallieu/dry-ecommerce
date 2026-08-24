<?php

namespace Tnt\Ecommerce\Contracts;

/**
 * Interface OrderInterface
 * @package Tnt\Ecommerce\Contracts
 */
interface OrderInterface
{
    /**
     * @param CartItemInterface $cartItem
     * @return mixed
     */
    public function add(CartItemInterface $cartItem);

    /**
     * @return iterable<int, OrderItemInterface>
     */
    public function getItems();

    /**
     * @param CustomerInterface $customer
     * @return mixed
     */
    public function setCustomer(CustomerInterface $customer);

    /**
     * @return CustomerInterface
     */
    public function getCustomer(): CustomerInterface;

    /**
     * @param FulfillmentInterface $fulfillmentMethod
     * @return mixed
     */
    public function setFulfillment(FulfillmentInterface $fulfillmentMethod);

    /**
     * @return FulfillmentInterface
     */
    public function getFulfillment(): FulfillmentInterface;

    /**
     * The subtotal frozen onto the order at checkout, in cents.
     *
     * @see \Tnt\Ecommerce\Money
     * @return int
     */
    public function getSubTotal(): int;

    /**
     * The total frozen onto the order at checkout, in cents.
     *
     * @see \Tnt\Ecommerce\Money
     * @return int
     */
    public function getTotal(): int;

    /**
     * The reduction frozen onto the order at checkout, in cents.
     *
     * @see \Tnt\Ecommerce\Money
     * @return int
     */
    public function getReduction(): int;

    /**
     * The tax frozen onto the order at checkout, in cents.
     *
     * Whether it is contained in {@see getTotal()} or was added to it depends
     * on the convention the order was placed under; see
     * {@see \Tnt\Ecommerce\Tax\PriceConvention}.
     *
     * @see \Tnt\Ecommerce\Money
     * @return int
     */
    public function getTax(): int;
}

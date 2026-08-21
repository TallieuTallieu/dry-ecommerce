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
}

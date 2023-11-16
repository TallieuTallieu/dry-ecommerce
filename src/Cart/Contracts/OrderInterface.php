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
     * @return array
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
     * @return float
     */
    public function getSubTotal(): float;

    /**
     * @return float
     */
    public function getTotal(): float;

    /**
     * @return float
     */
    public function getReduction(): float;
}
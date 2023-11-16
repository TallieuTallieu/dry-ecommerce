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
     * @return array
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
     * @return float
     */
    public function getFulfillmentCost(): float;

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

    /**
     * @return mixed
     */
    public function getIdentifier();
}
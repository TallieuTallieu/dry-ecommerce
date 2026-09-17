<?php

namespace Tnt\Ecommerce\Contracts;

/**
 * Interface OrderInterface
 * @package Tnt\Ecommerce\Contracts
 */
interface OrderInterface
{
    /**
     * Freeze one cart line onto this order, and answer the line that was
     * written — {@see \Tnt\Ecommerce\Cart\Cart::place()} needs it to copy
     * the parent/child links once every line exists.
     *
     * @param CartItemInterface $cartItem
     * @return OrderItemInterface
     */
    public function add(CartItemInterface $cartItem): OrderItemInterface;

    /**
     * @return iterable<int, OrderItemInterface>
     */
    public function getItems();

    /**
     * The provider's id for the attempt this order is currently waiting on,
     * or null. A gateway's `resumeUrl()` takes this. See docs/payment.md.
     *
     * @return string|null
     */
    public function getPaymentId(): ?string;

    /**
     * The key a gateway hands its provider so a double-submit is answered
     * with one payment. Re-minted at every placement.
     *
     * @return string
     */
    public function getPaymentKey(): string;

    /**
     * @param CustomerInterface $customer
     * @return mixed
     */
    public function setCustomer(CustomerInterface $customer);

    /**
     * The account link, or null for a guest order — the identity an order was
     * placed under is frozen on the order itself. See docs/customer.md.
     *
     * @return CustomerInterface|null
     */
    public function getCustomer(): ?CustomerInterface;

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

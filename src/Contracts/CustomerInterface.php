<?php

namespace Tnt\Ecommerce\Contracts;

/**
 * Who an order was placed by.
 *
 * Three getters over three fields, and deliberately nothing more. This is what
 * {@see \Tnt\Ecommerce\Model\Order} freezes as the identity of a checkout, so
 * anything a shop can pass to {@see CartInterface::checkout()} has to be able
 * to answer all three — an order that cannot say who placed it and where to
 * write to them is not an order anybody can invoice.
 *
 * Addresses are *not* here. They are a capability, asked for separately through
 * {@see HasAddressesInterface}, because a shop selling downloads has a customer
 * with a name and an email and no address at all.
 */
interface CustomerInterface
{
    /**
     * @return string
     */
    public function getFirstName(): string;

    /**
     * @return string
     */
    public function getLastName(): string;

    /**
     * The email address the order was placed with.
     *
     * Added in sc-11172, and the one breaking change in it: an implementation
     * of this interface that is not this package's
     * {@see \Tnt\Ecommerce\Model\Customer} has to grow a `getEmail()`. It is
     * here rather than on {@see HasAddressesInterface} because it is identity
     * and not delivery — it is how a shop reaches the person afterwards about
     * an order that has already been placed, which is true whether or not
     * anything was ever shipped to them.
     *
     * @return string
     */
    public function getEmail(): string;
}

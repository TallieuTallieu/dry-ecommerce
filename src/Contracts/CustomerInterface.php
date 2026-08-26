<?php

namespace Tnt\Ecommerce\Contracts;

/**
 * Who an order was placed by — the identity {@see \Tnt\Ecommerce\Model\Order}
 * freezes at checkout. Addresses are a separate capability
 * ({@see HasAddressesInterface}). See docs/customer.md.
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
     * The email address the order was placed with — identity, not delivery.
     *
     * @return string
     */
    public function getEmail(): string;

    /**
     * The company the account is in the name of, or '' for a person. Frozen
     * onto the order at checkout. A postal company line on an address block is
     * a different thing and is deliberately not here — see docs/addresses.md.
     *
     * @return string
     */
    public function getCompanyName(): string;

    /**
     * The customer's VAT number, or '' when they have none.
     *
     * @return string
     */
    public function getVatNumber(): string;
}

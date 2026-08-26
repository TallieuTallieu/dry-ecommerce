<?php

namespace Tnt\Ecommerce\Contracts;

use Tnt\Ecommerce\Address\AddressType;

/**
 * A customer who keeps an address book — a capability in the same shape as
 * {@see HasStockInterface}: checkout asks with `instanceof`, and a customer
 * that does not implement it freezes blank address columns. See
 * docs/addresses.md.
 */
interface HasAddressesInterface
{
    /**
     * The address this customer would have an order of this kind sent to, or
     * null when they have none — nothing is substituted from the other kind.
     *
     * @param AddressType $type
     * @return AddressInterface|null
     */
    public function getAddress(AddressType $type): ?AddressInterface;
}

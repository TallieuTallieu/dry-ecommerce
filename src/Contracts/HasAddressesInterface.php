<?php

namespace Tnt\Ecommerce\Contracts;

use Tnt\Ecommerce\Address\AddressType;

/**
 * A customer who keeps an address book.
 *
 * The same shape as {@see HasStockInterface} and {@see TaxableInterface}, and
 * for the same reason: {@see \Tnt\Ecommerce\Cart\Cart::checkout()} asks with
 * `instanceof`, and a customer that does not implement it is not asked. A shop
 * with its own customer class, or one selling something that is never delivered
 * anywhere, implements nothing extra and gets an order whose address columns
 * are blank — which is the truthful record of a checkout that had no address in
 * it, not a degraded one.
 *
 * Deliberately not folded into {@see CustomerInterface}. That interface is what
 * every checkout must answer — who placed this order — and it stays answerable
 * by three getters over three columns. Requiring an address book there would
 * make every implementer of a two-method contract go and model addresses before
 * it could check out.
 *
 * # One address per kind, out of a list of many
 *
 * The book is a list ({@see \Tnt\Ecommerce\Model\Customer::getAddresses()}), and
 * an order freezes exactly one address of each kind. Which one is a decision
 * this interface hands to the customer rather than making for it; this
 * package's {@see \Tnt\Ecommerce\Model\Customer} answers with the most recently
 * added of that kind unless the shop has said otherwise through
 * {@see \Tnt\Ecommerce\Model\Customer::useAddress()}.
 */
interface HasAddressesInterface
{
    /**
     * The address this customer would have an order of this kind sent to, or
     * null when they have none.
     *
     * Null is ordinary. A customer who has only ever given a delivery address
     * has no billing address, and saying so is better than handing back the
     * delivery one under another name.
     *
     * @param AddressType $type
     * @return AddressInterface|null
     */
    public function getAddress(AddressType $type): ?AddressInterface;
}

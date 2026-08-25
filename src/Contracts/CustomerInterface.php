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

    /**
     * The company the account is in the name of, or '' for a person.
     *
     * An account can be opened by a business, and then the company name and
     * the {@see getVatNumber()} beside it are what the account *is* — one
     * identity, not two facts that happen to travel together. Both are frozen
     * onto an order at checkout, because both are printed on the invoice and
     * neither may move after it is issued.
     *
     * # A postal company line is a different thing, and is not here yet
     *
     * An address block can carry a company of its own: an invoice goes to a
     * head office and a parcel goes to a branch, and a delivery label without
     * that name on it does not get past a post room. That is not this field,
     * and this field cannot stand in for it — one account has one company name
     * and a customer may post to several places.
     *
     * Deliberately deferred rather than solved. A shop that needs a different
     * name on the label has nowhere to put it today; when one does,
     * {@see AddressInterface} is where it goes, and this stays where it is.
     *
     * @return string
     */
    public function getCompanyName(): string;

    /**
     * The customer's VAT number, or '' when they have none.
     *
     * A business identity rather than an address: it belongs to whoever is
     * buying, not to any one of the places they receive post. The company
     * *name* is the other half of it and does sit on the address
     * ({@see AddressInterface::getCompany()}), because that one is part of a
     * postal block and can differ between where the invoice goes and where the
     * parcel goes. A VAT number cannot.
     *
     * '' rather than null for a shop selling to people. Every customer can
     * answer this honestly, which is what keeps it here rather than behind a
     * capability interface: unlike stock on a buyable, there is no customer for
     * whom the question is meaningless.
     *
     * @return string
     */
    public function getVatNumber(): string;
}

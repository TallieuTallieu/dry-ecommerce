<?php

namespace Tnt\Ecommerce\Contracts;

use Tnt\Ecommerce\Address\AddressType;

/**
 * A postal address, whether it is still editable or already frozen.
 *
 * Two things implement it, and the difference between them is the whole point
 * of this ticket:
 *
 * - {@see \Tnt\Ecommerce\Model\Address} — a row in a customer's address book. It
 *   is mutable, it can be deleted, and it says where a customer *currently*
 *   lives.
 * - {@see \Tnt\Ecommerce\Address\FrozenAddress} — the copy an order took at
 *   checkout, read back off the order's own columns. It says where one order
 *   actually went, and nothing can change it afterwards.
 *
 * Only the second is safe to print on an invoice. They share this interface so
 * that a template rendering an address does not have to be written twice, not
 * because they are interchangeable.
 *
 * Every field is a string, and a field a shop never collected is `''` rather
 * than null. An address with nothing in it is a real state — see
 * {@see AddressType::snapshotOf()} — and making callers distinguish `null` from
 * `''` on seven fields buys nothing an `if ($address->getStreet() === '')`
 * does not.
 */
interface AddressInterface
{
    /**
     * What this address is for.
     *
     * @return AddressType
     */
    public function getType(): AddressType;

    /**
     * The recipient's first name, which need not be the customer's.
     *
     * A parcel can go to somebody else — a gift, a colleague, a neighbour who
     * is in during the day — so the name travels with the address rather than
     * being taken from the account that placed the order.
     *
     * @return string
     */
    public function getFirstName(): string;

    /**
     * The recipient's last name.
     *
     * @return string
     */
    public function getLastName(): string;

    /**
     * @return string
     */
    public function getStreet(): string;

    /**
     * The house number, kept apart from the street.
     *
     * Separate columns because a carrier's API asks for them separately, and
     * splitting `'Nieuwstraat 12 bus 3'` back apart afterwards is guesswork.
     *
     * @return string
     */
    public function getNumber(): string;

    /**
     * @return string
     */
    public function getPostalCode(): string;

    /**
     * @return string
     */
    public function getCity(): string;

    /**
     * @return string
     */
    public function getCountry(): string;
}

<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Address;

use Tnt\Ecommerce\Contracts\AddressInterface;

/**
 * What an address in a customer's book is for.
 *
 * Two cases, because a shop has two questions to ask about an order and no
 * more: where the invoice goes, and where the parcel goes. Anything finer — a
 * "home" and a "work" address, a default flag, a nickname — is a shop's own
 * labelling of its customers' addresses, and a package that guessed at those
 * labels would be wrong for every shop that labels differently while giving
 * nothing to the one place this package actually reads the type: choosing what
 * an order freezes.
 *
 * # It owns the column names, not just the label
 *
 * The order's frozen copy is a set of `billing_*` and `shipping_*` columns, and
 * the case value *is* that prefix. So {@see snapshotOf()} produces the columns
 * and their values in one place for both cases, {@see columns()} hands the same
 * names to the revision that declares them, and the schema cannot drift from
 * what checkout writes into it — adding a field means adding one line here and
 * both sides follow.
 *
 * The alternative was a bare label with `switch ($type)` in the order, the
 * revision and the invoice template. That is three copies of the same mapping,
 * and the one that gets forgotten is the one that silently writes a delivery
 * address into a billing column.
 *
 * @see \Tnt\Ecommerce\Model\Order::freezeCustomer()
 */
enum AddressType: string
{
    /**
     * Where the invoice goes.
     */
    case Billing = 'billing';

    /**
     * Where the parcel goes.
     */
    case Shipping = 'shipping';

    /**
     * The order columns this kind of address freezes into, and what goes in
     * them.
     *
     * A null address gives every column an empty string rather than a null. A
     * shop that collected one address gets the other side blank, and blank is
     * the honest record of "none was given" — see
     * {@see \Tnt\Ecommerce\Model\Order::freezeCustomer()} for why nothing is
     * copied across from the side that was filled in.
     *
     * @param AddressInterface|null $address The address as it stands right now.
     * @return array<string, string> Column name to value.
     */
    public function snapshotOf(?AddressInterface $address): array
    {
        $prefix = $this->value . '_';

        return [
            $prefix . 'first_name' => $address?->getFirstName() ?? '',
            $prefix . 'last_name' => $address?->getLastName() ?? '',
            $prefix . 'street' => $address?->getStreet() ?? '',
            $prefix . 'number' => $address?->getNumber() ?? '',
            $prefix . 'postal_code' => $address?->getPostalCode() ?? '',
            $prefix . 'city' => $address?->getCity() ?? '',
            $prefix . 'country' => $address?->getCountry() ?? '',
        ];
    }

    /**
     * The names of those columns, for the revision that declares them.
     *
     * Read off {@see snapshotOf()} rather than listed again, so that the table
     * and the write are the same list by construction.
     *
     * @return array<int, string>
     */
    public function columns(): array
    {
        return array_keys($this->snapshotOf(null));
    }
}

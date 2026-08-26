<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Address;

use Tnt\Ecommerce\Contracts\AddressInterface;

/**
 * What an address in a customer's book is for: where the invoice goes, or
 * where the parcel goes. The case value is also the prefix of the order's
 * frozen columns — {@see snapshotOf()} and {@see columns()} keep the schema
 * and the write the same list by construction.
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
     * them. A null address gives every column '' — the honest record of "none
     * was given".
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

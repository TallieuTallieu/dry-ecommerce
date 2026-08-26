<?php

namespace Tnt\Ecommerce\Contracts;

use Tnt\Ecommerce\Address\AddressType;

/**
 * A postal address — the live {@see \Tnt\Ecommerce\Model\Address} or the
 * frozen {@see \Tnt\Ecommerce\Address\FrozenAddress}; only the frozen copy is
 * safe on an invoice. Fields a shop never collected are `''`, not null.
 * See docs/addresses.md.
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
     * Whether this is the one of its kind a checkout takes by default. A
     * frozen copy on an order always answers false.
     *
     * @return bool
     */
    public function isDefault(): bool;

    /**
     * @return string
     */
    public function getStreet(): string;

    /**
     * The house number, kept apart from the street — carriers ask for them
     * separately.
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

<?php

namespace Tnt\Ecommerce;

use RuntimeException;
use Tnt\Ecommerce\Address\AddressType;

/**
 * An address book that cannot say which address a checkout should use —
 * several of a kind with no default marked, or more than one marked. Raised
 * rather than guessed: a wrong parcel looks like a perfect order. Mark a
 * default or use {@see \Tnt\Ecommerce\Model\Customer::useAddress()}.
 */
final class AmbiguousAddress extends RuntimeException
{
    /**
     * @param AddressType $type The kind of address that could not be chosen.
     * @param int $count How many of them the book holds.
     * @param bool $tooManyDefaults Whether the trouble is that more than one
     *                              is marked, rather than none.
     */
    public function __construct(
        private readonly AddressType $type,
        private readonly int $count,
        private readonly bool $tooManyDefaults = false
    ) {
        parent::__construct(
            $tooManyDefaults
                ? sprintf(
                    'This address book marks more than one %s address as the ' .
                        'default, so a checkout cannot tell which one to use. ' .
                        'Mark one, or name the address for this checkout with ' .
                        'Customer::useAddress().',
                    $type->value
                )
                : sprintf(
                    'This address book holds %d %s addresses and marks none ' .
                        'of them as the default, so a checkout cannot tell ' .
                        'which one to use. Mark one, or name the address for ' .
                        'this checkout with Customer::useAddress().',
                    $count,
                    $type->value
                )
        );
    }

    /**
     * The kind of address that could not be chosen.
     */
    public function getType(): AddressType
    {
        return $this->type;
    }

    /**
     * How many addresses of that kind the book holds.
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * Whether more than one was marked, rather than none.
     */
    public function hasTooManyDefaults(): bool
    {
        return $this->tooManyDefaults;
    }
}

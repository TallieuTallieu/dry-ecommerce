<?php

namespace Tnt\Ecommerce;

use RuntimeException;
use Tnt\Ecommerce\Address\AddressType;

/**
 * An address book that cannot say which address a checkout should use.
 *
 * A customer may keep several addresses of a kind — home and work, this
 * warehouse and that one — and one of them is marked the default. When none is
 * marked and there is more than one to choose between, there is no answer, and
 * this is raised rather than one of them being picked.
 *
 * # Why this refuses rather than guesses
 *
 * Because the two failures are not the same size. A checkout that stops asks
 * somebody to mark a default, which takes a moment. A checkout that guesses
 * sends the parcel to the wrong address, and nothing looks wrong until it
 * arrives there — no error, no warning, an order that reads perfectly and a
 * customer who did not get their delivery.
 *
 * The same reasoning as {@see NotAnAddressType}, and the same as the package
 * takes with money: an amount it cannot work out exactly is refused rather
 * than approximated.
 *
 * # Avoiding it
 *
 * Mark one address of each kind as the default, which is what a shop's address
 * book screen is for. Or name the address for this checkout with
 * {@see \Tnt\Ecommerce\Model\Customer::useAddress()}, which overrides the
 * default and settles the question for the request.
 *
 * A book holding one address of a kind is never ambiguous: with nothing to
 * choose between, that one is the answer whether it is marked or not.
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

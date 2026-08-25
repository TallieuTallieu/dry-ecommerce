<?php

namespace Tnt\Ecommerce;

use InvalidArgumentException;
use Tnt\Ecommerce\Address\AddressType;

/**
 * A value in `ecommerce_address.type` that is not an {@see AddressType}.
 *
 * Thrown by {@see \Tnt\Ecommerce\Model\Address::getType()} rather than quietly
 * reading the row as billing. An address whose type cannot be read is an
 * address nobody can say the purpose of, and guessing at it puts a delivery
 * address on an invoice or a billing address on a parcel — a wrong answer that
 * looks like a right one all the way to the customer's door.
 *
 * The deliberate contrast is {@see \Tnt\Ecommerce\Model\Order} reading a
 * missing `prices` as inclusive. That default is safe because it is the reading
 * whose totals match what those rows already hold; there is no such reading
 * here, because a billing and a shipping address are equally plausible and only
 * one of them is right.
 *
 * Carries the text it refused, because that text came out of a column somebody
 * will have to go and look at.
 *
 * Extends `InvalidArgumentException`, as do {@see AmountTooLarge},
 * {@see NotAnAmount} and {@see UnsupportedRate}.
 */
final class NotAnAddressType extends InvalidArgumentException
{
    /**
     * @param string $text The value that was refused.
     */
    public function __construct(private readonly string $text)
    {
        parent::__construct(
            sprintf(
                "'%s' is not an address type. An address is for %s.",
                $text,
                implode(
                    ' or ',
                    array_map(
                        static fn(AddressType $type): string => $type->value,
                        AddressType::cases()
                    )
                )
            )
        );
    }

    /**
     * The value that was refused.
     *
     * @return string
     */
    public function getText(): string
    {
        return $this->text;
    }
}

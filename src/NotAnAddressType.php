<?php

namespace Tnt\Ecommerce;

use InvalidArgumentException;
use Tnt\Ecommerce\Address\AddressType;

/**
 * A value in `ecommerce_address.type` that is not an {@see AddressType} —
 * refused rather than guessed, because billing and shipping are equally
 * plausible and only one is right.
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

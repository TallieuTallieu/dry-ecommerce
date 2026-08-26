<?php

namespace Tnt\Ecommerce;

use InvalidArgumentException;

/**
 * Text {@see Money::fromDecimal()} cannot read as an amount of cents — not an
 * amount, finer than a cent, or past what an int holds. See docs/money.md.
 */
final class NotAnAmount extends InvalidArgumentException
{
    /**
     * @param string $text The text that was refused.
     * @param string $message
     */
    private function __construct(private readonly string $text, string $message)
    {
        parent::__construct($message);
    }

    /**
     * Text that is not an amount in units at all.
     *
     * @param string $text The text that was refused.
     * @return self
     */
    public static function unreadable(string $text): self
    {
        return new self(
            $text,
            sprintf(
                "Money cannot read '%s' as an amount: an amount is digits, " .
                    'with an optional leading - and at most two decimal ' .
                    'places after a full stop. A currency symbol, a comma and ' .
                    'a thousands separator are the project\'s to strip first.',
                $text
            )
        );
    }

    /**
     * An amount with more precision than a cent.
     *
     * @param string $text The text that was refused.
     * @return self
     */
    public static function finerThanACent(string $text): self
    {
        return new self(
            $text,
            sprintf(
                "Money cannot read '%s' as an amount: it is finer than a " .
                    'cent, and rounding it here would change a price. Round ' .
                    'it where the extra precision came from.',
                $text
            )
        );
    }

    /**
     * An amount of cents too large to be an `int`.
     *
     * @param string $text The text that was refused.
     * @return self
     */
    public static function tooLarge(string $text): self
    {
        return new self(
            $text,
            sprintf(
                "Money cannot read '%s' as an amount: in cents it is past " .
                    'what a PHP int holds, so there is no exact amount to ' .
                    'return.',
                $text
            )
        );
    }

    /**
     * The text that was refused.
     */
    public function getText(): string
    {
        return $this->text;
    }
}

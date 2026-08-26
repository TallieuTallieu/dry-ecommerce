<?php

namespace Tnt\Ecommerce;

use InvalidArgumentException;

/**
 * A split {@see Money::apportion()} cannot make add back up: a negative
 * amount or weight makes the floored shares overshoot, so both are refused
 * rather than approximated. See docs/money.md.
 */
final class NotApportionable extends InvalidArgumentException
{
    /**
     * @param int $refused The negative figure that was refused, in cents.
     * @param string $message
     */
    private function __construct(private readonly int $refused, string $message)
    {
        parent::__construct($message);
    }

    /**
     * An amount that is not a sum of money to divide up.
     *
     * @param int $amount The amount that was refused, in cents.
     * @return self
     */
    public static function negativeAmount(int $amount): self
    {
        return new self(
            $amount,
            sprintf(
                'Money cannot apportion %d cents: an amount to split is not ' .
                    'negative. The shares are floored and the cents left over ' .
                    'handed out one each, and there is nothing left over to ' .
                    'hand out below zero. Split what was taken off, and put ' .
                    'the sign back where it belongs.',
                $amount
            )
        );
    }

    /**
     * A weight that is not a share of what is being split.
     *
     * @param int $weight The weight that was refused.
     * @param int|string $index Where it sat, for finding it again.
     * @return self
     */
    public static function negativeWeight(int $weight, int|string $index): self
    {
        return new self(
            $weight,
            sprintf(
                'Money cannot apportion across a weight of %d, at %s: a weight ' .
                    'is a share of the whole and is not negative. One that is ' .
                    'takes the shares past the amount, and the parts stop ' .
                    'adding back up to it.',
                $weight,
                is_int($index)
                    ? sprintf('index %d', $index)
                    : "'" . $index . "'"
            )
        );
    }

    /**
     * The negative figure that was refused, in cents.
     */
    public function getRefused(): int
    {
        return $this->refused;
    }
}

<?php

namespace Tnt\Ecommerce;

use InvalidArgumentException;

/**
 * A split {@see Money::apportion()} cannot make add back up.
 *
 * Apportioning promises one thing: the parts sum to the amount, exactly. It
 * keeps that promise by flooring every share and handing the cents left over
 * to the largest remainders, which works because flooring can only ever leave
 * money on the table — never take more than there was.
 *
 * A negative figure breaks that. `intdiv()` truncates towards zero, so it
 * rounds a negative product *up*, and the floored shares can then overshoot
 * the amount rather than fall short of it. There is nothing left over to hand
 * out, the correction step has nothing to correct, and the parts come back
 * summing to more than they were split from — `apportion(5, [-1, -1, 4])` gave
 * `[-2, -2, 10]`, which is 6. A cent invented in silence, on the discount that
 * decides what a customer is taxed on.
 *
 * So both kinds of negative are refused here rather than approximated, in
 * keeping with the rest of {@see Money}: an amount that is not a positive sum
 * to divide up, and a weight that is not a positive share of it. Neither is
 * reachable from a cart of real prices, which is precisely why an exception
 * beats an answer — whatever produced one is wrong somewhere further up.
 *
 * Extends `InvalidArgumentException`, as do {@see NotAnAmount},
 * {@see AmountTooLarge} and {@see UnsupportedRate}.
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

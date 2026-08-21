<?php

namespace Tnt\Ecommerce;

/**
 * Money, and the one rounding rule the package applies to it.
 *
 * # Money is an integer number of cents
 *
 * Every monetary value in this package — a price, a line total, a subtotal, a
 * fulfillment cost, a reduction, a tax amount — is an `int` counting the
 * smallest unit of the currency. €12.25 is `1225`. No `float` appears in any
 * money-carrying contract, model property or column, because this package adds
 * money up in loops: `Cart::getSubTotal()` accumulates one line total per item,
 * and float accumulation drifts. `0.1 + 0.2 + 0.3` is not `0.6`; `10 + 20 + 30`
 * is always `60`.
 *
 * # The rounding rule
 *
 * Integers do not remove rounding, they concentrate it. Multiplying an amount
 * by a *rate* — VAT at 6%, 12% or 21%, a percentage discount — produces
 * fractional cents, and something has to decide what happens to them. The rule
 * this package applies, and the rule an implementation of
 * {@see \Tnt\Ecommerce\Contracts\TaxRateInterface} or
 * {@see \Tnt\Ecommerce\Contracts\CouponInterface} is expected to follow, has
 * two halves:
 *
 * **1. Round half away from zero.** A result of exactly half a cent rounds up
 * in magnitude: 21% of `50` is `10.5`, and becomes `11`. This is what an
 * invoice reader expects and what {@see round()} does by default; banker's
 * rounding is deliberately not used, because on a single invoice line it looks
 * arbitrary.
 *
 * **2. Round once, on the smallest amount the rate genuinely applies to.**
 * Per-line VAT is computed and rounded per line; a cart-level percentage
 * discount is computed and rounded once, on the subtotal. Totals are then plain
 * integer sums of amounts that have already been rounded — never a rate applied
 * to a total.
 *
 * The second half is a real choice, not a detail, because rounding per line and
 * rounding the total give different answers. Two lines of `1250` at 21%:
 *
 * - per line — `263 + 263 = 526`
 * - on the total — 21% of `2500` = `525`
 *
 * This package takes `526`. Each line is a figure that gets printed and can be
 * checked on its own, so the total has to be the sum of the printed lines. A
 * total that does not add up from the figures above it is the worse of the two
 * failures on an invoice.
 *
 * # Using it
 *
 * ```php
 * $vat = Money::percentageOf($line->getPrice(), 21); // 21% VAT on one line
 * $off = Money::percentageOf($cart->getSubTotal(), 10); // 10% off the cart
 * ```
 */
final class Money
{
    /**
     * How finely a rate is honoured.
     *
     * A rate is turned into this many integer steps per percent before it is
     * multiplied, so the arithmetic below is integer throughout and a rate is
     * exact to four decimal places of a percent — 21, 21.5 and 0.0625 all land
     * where they should.
     */
    private const RATE_SCALE = 10000;

    /**
     * Static only; there is no `Money` value to hold, only `int` cents.
     */
    private function __construct() {}

    /**
     * A percentage of an amount, in cents, rounded half away from zero.
     *
     * The single place the rounding rule described above is implemented. Both
     * arguments may be negative; the result keeps the sign of the product and
     * a half-cent still rounds away from zero.
     *
     * The rate is reduced to its smallest whole fraction before the amount is
     * multiplied by it — 21% becomes 21/100, not 210000/1000000 — which keeps
     * the intermediate product small enough that amounts up to roughly 4×10^17
     * cents stay exact. Real orders are nowhere near that; the reduction is
     * what makes the guarantee unqualified rather than approximate.
     *
     * @param int $amount The amount the rate applies to, in cents.
     * @param int|float $percentage The rate, as a percentage: 21 means 21%.
     * @return int The rounded result, in cents.
     */
    public static function percentageOf(int $amount, int|float $percentage): int
    {
        $numerator = (int) round($percentage * self::RATE_SCALE);
        $denominator = 100 * self::RATE_SCALE;

        $divisor = self::greatestCommonDivisor(
            $numerator < 0 ? -$numerator : $numerator,
            $denominator
        );

        return self::divideRoundingHalfAwayFromZero(
            $amount * intdiv($numerator, $divisor),
            intdiv($denominator, $divisor)
        );
    }

    /**
     * Integer division that rounds a half away from zero.
     *
     * `intdiv(2n + d, 2d)` is `floor((n + d/2) / d)`, which is exactly
     * round-half-up for a non-negative `n`; the sign is peeled off first and
     * put back afterwards so that a negative amount rounds away from zero
     * rather than towards it.
     *
     * @param int $numerator
     * @param int $denominator Positive.
     * @return int
     */
    private static function divideRoundingHalfAwayFromZero(
        int $numerator,
        int $denominator
    ): int {
        $isNegative = $numerator < 0;
        $magnitude = $isNegative ? -$numerator : $numerator;

        $rounded = intdiv(2 * $magnitude + $denominator, 2 * $denominator);

        return $isNegative ? -$rounded : $rounded;
    }

    /**
     * Euclid, so that a rate can be reduced to its smallest whole fraction.
     *
     * A zero numerator reduces against the denominator itself, which collapses
     * a rate of 0% to 0/1 rather than dividing by zero.
     *
     * @param int $a Non-negative.
     * @param int $b Positive.
     * @return int
     */
    private static function greatestCommonDivisor(int $a, int $b): int
    {
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }

        return $a;
    }
}

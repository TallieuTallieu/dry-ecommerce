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
 *
 * # What it refuses
 *
 * The exactness above is a promise, so the two ways of losing it are refused
 * rather than approximated: an amount past the ceiling for its rate raises
 * {@see AmountTooLarge}, and a rate finer or larger than the scale can hold —
 * or `INF`, or `NAN` — raises {@see UnsupportedRate}. Neither is reachable
 * with a real order and a real VAT rate. Both are reachable by handing this
 * class something that is not cents, or not a percentage, which is exactly
 * when an exception beats an answer.
 */
final class Money
{
    /**
     * How finely a rate is honoured.
     *
     * A rate is turned into this many integer steps per percent before it is
     * multiplied, so the arithmetic below is integer throughout and a rate is
     * exact to four decimal places of a percent — 21, 21.5 and 0.0625 all land
     * where they should. 0.0001% is therefore the finest rate there is; a
     * non-zero rate below it raises {@see UnsupportedRate} rather than
     * collapsing to 0% and taking nothing off.
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
     * the intermediate product small. Even so the arithmetic has a ceiling, and
     * the amount is multiplied twice on the way to an answer: once by the rate,
     * and once more by 2 inside the rounding. At 21% that puts the largest
     * exact amount at 219,604,096,115,589,897 cents, roughly 2.19×10^17 — half
     * of what the rate alone would suggest, and still far past any real order.
     *
     * Nothing is approximated past that point. An amount over the ceiling
     * raises {@see AmountTooLarge}, and a rate the scale cannot hold raises
     * {@see UnsupportedRate}, rather than either one silently leaving `int`
     * behind for a `float` and answering with money that is merely close.
     *
     * @param int $amount The amount the rate applies to, in cents.
     * @param int|float $percentage The rate, as a percentage: 21 means 21%.
     * @return int The rounded result, in cents.
     *
     * @throws AmountTooLarge If the amount is over the ceiling for this rate.
     * @throws UnsupportedRate If the rate is finer, or larger, than the scale
     *                         can hold, or is not a finite percentage.
     */
    public static function percentageOf(int $amount, int|float $percentage): int
    {
        [$rateNumerator, $rateDenominator] = self::reduceRate($percentage);

        self::refuseAnAmountOverTheCeiling(
            $amount,
            $percentage,
            $rateNumerator,
            $rateDenominator
        );

        return self::divideRoundingHalfAwayFromZero(
            $amount * $rateNumerator,
            $rateDenominator
        );
    }

    /**
     * A rate as a whole fraction, reduced, or an exception if it is not one.
     *
     * Scaling happens in `float`, because a rate is allowed to arrive as one;
     * this is the boundary where that `float` is checked and turned into the
     * pair of integers the rest of the arithmetic runs on. A rate that does not
     * survive the trip is refused here rather than carried forward, so no
     * caller downstream has to wonder whether it did.
     *
     * @param int|float $percentage The rate, as a percentage.
     * @return array{int, int} The numerator, which carries the sign, and the
     *                         denominator, which is positive.
     *
     * @throws UnsupportedRate
     */
    private static function reduceRate(int|float $percentage): array
    {
        $scaled = round((float) $percentage * self::RATE_SCALE);

        // `(float) PHP_INT_MAX` rounds up to 2^63, one past the largest int, so
        // `>=` is what keeps the cast below inside the range PHP guarantees.
        // `is_finite()` covers NAN and INF, which no comparison would catch.
        if (!is_finite($scaled) || abs($scaled) >= (float) PHP_INT_MAX) {
            throw UnsupportedRate::notRepresentable($percentage);
        }

        $numerator = (int) $scaled;

        if ($numerator === 0 && (float) $percentage !== 0.0) {
            throw UnsupportedRate::tooFine($percentage, 1 / self::RATE_SCALE);
        }

        $denominator = 100 * self::RATE_SCALE;

        $divisor = self::greatestCommonDivisor(
            $numerator < 0 ? -$numerator : $numerator,
            $denominator
        );

        return [intdiv($numerator, $divisor), intdiv($denominator, $divisor)];
    }

    /**
     * Refuses an amount this rate cannot be applied to exactly.
     *
     * {@see divideRoundingHalfAwayFromZero()} works on `2 * amount * rate +
     * denominator`, so that whole expression is what has to fit in an `int`.
     * The ceiling is therefore derived by dividing rather than by multiplying —
     * working out the limit must not overflow the very thing it is checking
     * for.
     *
     * @param int $amount The amount the rate applies to, in cents.
     * @param int|float $percentage The rate, for the exception to report.
     * @param int $rateNumerator
     * @param int $rateDenominator Positive.
     * @return void
     *
     * @throws AmountTooLarge
     */
    private static function refuseAnAmountOverTheCeiling(
        int $amount,
        int|float $percentage,
        int $rateNumerator,
        int $rateDenominator
    ): void {
        $magnitude = $rateNumerator < 0 ? -$rateNumerator : $rateNumerator;

        // A rate of 0% multiplies everything down to nothing; no amount of
        // cents can overflow that.
        if ($magnitude === 0) {
            return;
        }

        $maximumAmount = intdiv(
            intdiv(PHP_INT_MAX - $rateDenominator, 2),
            $magnitude
        );

        if ($amount > $maximumAmount || $amount < -$maximumAmount) {
            throw new AmountTooLarge($amount, $percentage, $maximumAmount);
        }
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

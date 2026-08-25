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
 * $line = Money::lineTotal($buyable->getPrice(), 3); // three of something
 * $shown = Money::toDecimal($cart->getTotal()); // 5250 -> '52.50'
 * $price = Money::fromDecimal($input); // '12.25' -> 1225
 * ```
 *
 * {@see toDecimal()} is as far as this class goes towards display. It writes
 * cents out exactly, so that nobody reaches for `$cents / 100` and lets a
 * `float` back in on the last step. A currency symbol, a comma for a decimal
 * point and a thousands separator are the project's to add.
 *
 * {@see fromDecimal()} is the same boundary in the other direction, and the
 * more important of the two: money leaves this package as a figure on a page,
 * but it *enters* it as text from an admin field, a config value or a price
 * import, and that is where a wrong amount gets in.
 *
 * # What it refuses
 *
 * The exactness above is a promise, so the ways of losing it are refused rather
 * than approximated: an amount past the ceiling for its rate raises
 * {@see AmountTooLarge}, a rate finer or larger than the scale can hold — or
 * `INF`, or `NAN` — raises {@see UnsupportedRate}, and a split that could not
 * add back up raises {@see NotApportionable}. None of the three is reachable
 * with a real order and a real VAT rate. All three are reachable by handing
 * this class something that is not cents, or not a percentage, which is exactly
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
     * The part of an amount that *is* the percentage, rounded half away from
     * zero.
     *
     * The other direction from {@see percentageOf()}, and the one a shop
     * quoting tax-inclusive prices needs. `percentageOf(1250, 21)` answers "21%
     * added to €12.50 is 263 cents". This answers "of €12.50 that already has
     * 21% in it, 217 cents is the 21%".
     *
     * The two are not the same sum and neither is a rounding of the other:
     * `amount x r/100` against `amount x r/(100 + r)`. Using the first where
     * the second belongs over-reports the tax on every line by the rate itself
     * — 21% too much at 21% — which is silently wrong on an invoice rather
     * than obviously wrong.
     *
     * A Belgian consumer price includes its VAT, so this is the operation that
     * turns a shelf price into the "of which VAT" line beneath it. Which of the
     * two applies is not something this package can infer; see
     * {@see \Tnt\Ecommerce\Tax\PriceConvention}.
     *
     * @param int $amount The amount with the percentage already in it, in cents.
     * @param int|float $percentage The rate, as a percentage: 21 means 21%.
     * @return int The part of it that is the rate, in cents.
     *
     * @throws AmountTooLarge If the amount is over the ceiling for this rate.
     * @throws UnsupportedRate If the rate is one the scale cannot hold, or is
     *                         -100% or below, at which point an amount cannot
     *                         contain it.
     */
    public static function percentageIn(int $amount, int|float $percentage): int
    {
        [$rateNumerator, $rateDenominator] = self::reduceRate($percentage);

        // The whole of the amount is 100% *plus* the rate, rather than 100%, so
        // the denominator carries the rate as well. Reducing again keeps the
        // pair as small as the fraction allows: 21/121 rather than 21/121 over
        // a common scale.
        $whole = $rateDenominator + $rateNumerator;

        if ($whole <= 0) {
            throw UnsupportedRate::cannotBeContained($percentage);
        }

        $divisor = self::greatestCommonDivisor(
            $rateNumerator < 0 ? -$rateNumerator : $rateNumerator,
            $whole
        );

        $numerator = intdiv($rateNumerator, $divisor);
        $denominator = intdiv($whole, $divisor);

        self::refuseAnAmountOverTheCeiling(
            $amount,
            $percentage,
            $numerator,
            $denominator
        );

        return self::divideRoundingHalfAwayFromZero(
            $amount * $numerator,
            $denominator
        );
    }

    /**
     * Split an amount across weights so the parts add back up to it exactly.
     *
     * Dividing money proportionally leaves fractions of a cent, and dropping
     * them loses money while rounding them all up invents it. So the shares are
     * floored, and the cents left over are handed out one each to the shares
     * whose fraction was largest — the largest remainder method. Whatever the
     * weights, `array_sum($result) === $amount`.
     *
     * That promise is why a negative amount or a negative weight raises
     * {@see NotApportionable} rather than being split anyway. Flooring only
     * holds the sum together while it can leave money on the table; below zero
     * `intdiv()` truncates the other way, the shares overshoot, and there is
     * nothing left over for the correction step to hand out. Refused here, as
     * everything else this class cannot do exactly is refused, rather than
     * returning parts that quietly do not add up.
     *
     * A cart-level coupon is the reason this exists. The discount comes off the
     * cart, but tax is worked out per line, so the reduction has to reach the
     * lines somehow, and it has to arrive complete: a cart discounted by 250
     * that only takes 249 off the lines is a cent of tax computed on money
     * nobody paid.
     *
     * Ties go to the earlier weight, so the same input always gives the same
     * split. Two lines with equal weights and one cent to spare — the first
     * line gets it, every time, rather than it depending on sort order.
     *
     * @param int $amount The amount to split, in cents.
     * @param array<int, int> $weights What to split it in proportion to.
     * @return array<int, int> One share per weight, in the same order, summing
     *                         to `$amount`. All zero when the weights are.
     *
     * @throws NotApportionable If the amount, or any weight, is negative.
     */
    public static function apportion(int $amount, array $weights): array
    {
        if ($amount < 0) {
            throw NotApportionable::negativeAmount($amount);
        }

        $total = 0;

        // Adding the weights up is where each one is looked at anyway, so it is
        // also where each one is checked. Nothing below runs on a weight that
        // has not been through here.
        foreach ($weights as $index => $weight) {
            if ($weight < 0) {
                throw NotApportionable::negativeWeight($weight, $index);
            }

            $total += $weight;
        }

        if ($total === 0) {
            return array_fill_keys(array_keys($weights), 0);
        }

        $shares = [];
        $remainders = [];

        foreach ($weights as $index => $weight) {
            $product = $amount * $weight;

            $shares[$index] = intdiv($product, $total);
            $remainders[$index] = $product % $total;
        }

        // What flooring every share left behind, handed out a cent at a time.
        // Both the amount and every weight are known non-negative by now, so
        // every share fell short of the exact figure by less than one cent and
        // none overshot it: the count is neither negative nor larger than the
        // number of weights.
        $left = $amount - array_sum($shares);

        if ($left > 0) {
            arsort($remainders);

            foreach (array_slice(array_keys($remainders), 0, $left) as $index) {
                $shares[$index]++;
            }
        }

        return $shares;
    }

    /**
     * What a line of a cart or an order comes to, in cents.
     *
     * A quantity of something at a price each. Both cart item implementations
     * answer `getPrice()` with this, so the two multiplications that used to
     * sit apart in {@see \Tnt\Ecommerce\Model\CartItem} and
     * {@see \Tnt\Ecommerce\Cart\InMemoryCartItem} are one multiplication here.
     * The rounding rule owns one operation on money; this is the other one, and
     * it belongs in the same class.
     *
     * There is no ceiling to trip over, unlike {@see percentageOf()}: a line
     * total is a plain product of a price and a quantity the shop itself sets,
     * with no rate and no rounding in it.
     *
     * @param int $unitPrice The price of one, in cents.
     * @param int $quantity How many.
     * @return int The line total, in cents.
     */
    public static function lineTotal(int $unitPrice, int $quantity): int
    {
        return $unitPrice * $quantity;
    }

    /**
     * An amount of cents written out in units, exactly.
     *
     * `1225` becomes `'12.25'`. Two decimal places always, a full stop between
     * them, a leading `-` when the amount is negative, and no separator between
     * thousands.
     *
     * This is deliberately not a currency format. There is no symbol, no
     * comma-for-a-decimal-point, no thousands separator and no locale, because
     * those differ per shop and per template and would drag `ext-intl` into a
     * package that does not otherwise need it. Displaying money is the
     * project's job; the job here is to get out of cents without a `float`
     * doing it. `$cents / 100` is the thing this exists to replace — it puts
     * back, at the very last step, the representation the rest of the package
     * spends its time keeping out.
     *
     * A `string` and not a `float` for the same reason. The whole range of an
     * `int` survives this, down to `PHP_INT_MIN`.
     *
     * @param int $cents
     * @return string
     */
    public static function toDecimal(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';

        // intdiv() and % both truncate towards zero and both keep the sign, so
        // each part is negated on its own rather than the amount as a whole.
        // Negating the amount would overflow on PHP_INT_MIN; negating a
        // hundredth of it cannot.
        $units = intdiv($cents, 100);
        $hundredths = $cents % 100;

        return sprintf(
            '%s%d.%02d',
            $sign,
            $units < 0 ? -$units : $units,
            $hundredths < 0 ? -$hundredths : $hundredths
        );
    }

    /**
     * An amount written in units, read back into cents.
     *
     * The inverse of {@see toDecimal()}, and the door money comes in through.
     * An admin field, a config value, a price import: money arrives as text,
     * and text is where a wrong amount is easiest to introduce and hardest to
     * see. `'12.25'` becomes `1225`.
     *
     * Reads what `toDecimal()` writes, and the shorter forms a person types —
     * `'12.5'` is 1250 and `'12'` is 1200 — with surrounding space ignored.
     * Everything else raises {@see NotAnAmount}, for one of three reasons:
     *
     * - it is not an amount at all, which a plain `(int)` cast would have read
     *   as `0` — and `0` is a believable price;
     * - it is finer than a cent, like `'12.255'`, which this class could round
     *   but will not, because rounding it would change a price nobody asked to
     *   change;
     * - in cents it is past what an `int` holds.
     *
     * Symmetrically with `toDecimal()`, no locale is understood: `'12,25'`,
     * `'1,234.56'` and `'€ 12,25'` are all refused. Whatever formats an amount
     * for a person is what unformats it again.
     *
     * No arithmetic happens below, only text, which is what keeps the last
     * `float` out of the money path — see the comment on the concatenation.
     *
     * @param string $amount
     * @return int The amount, in cents.
     *
     * @throws NotAnAmount If the text is not an exact amount of cents.
     */
    public static function fromDecimal(string $amount): int
    {
        $trimmed = trim($amount);

        if (preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $trimmed, $parts) !== 1) {
            throw NotAnAmount::unreadable($amount);
        }

        $decimals = $parts[3] ?? '';

        if (strlen($decimals) > 2) {
            throw NotAnAmount::finerThanACent($amount);
        }

        // Appending two decimal places to the units *is* the multiplication by
        // 100, done in text. Nothing is added or multiplied here, so nothing
        // can overflow into a float on the way: the only question left is
        // whether the figure fits in an int, which is the one filter_var()
        // answers.
        $digits = ltrim($parts[2] . str_pad($decimals, 2, '0'), '0');
        $cents = filter_var(
            $parts[1] . ($digits === '' ? '0' : $digits),
            FILTER_VALIDATE_INT
        );

        if ($cents === false) {
            throw NotAnAmount::tooLarge($amount);
        }

        return $cents;
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

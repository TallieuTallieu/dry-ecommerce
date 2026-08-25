<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Tax;

use Tnt\Ecommerce\Money;

/**
 * Whether the prices a shop quotes have tax in them.
 *
 * The single fact this package could never infer, and the one everything about
 * tax turns on. A price of `1250` at 21% is either €12.50 of which €2.17 is
 * VAT, or €12.50 plus €2.63 of VAT that makes €15.13 — and the two answers
 * differ by the whole tax amount, not by a rounding.
 *
 * A Belgian consumer price includes its VAT, because a shopper is entitled to
 * see what they will pay. A price quoted between businesses conventionally
 * does not, because the buyer reclaims the VAT and cares about the net. Both
 * are ordinary; neither is a default the other can live with.
 *
 * So a shop says which it means, in `ecommerce.prices`, and an order records
 * the answer it was placed under. That second part is not bookkeeping: without
 * it, a shop that switches convention would reprint every old invoice with a
 * different total from the one it charged.
 *
 * # What each one does to a cart
 *
 * The same cart both ways: two lines of 1250 at 21%, delivered for 475.
 *
 * ```
 * INCLUSIVE                        EXCLUSIVE
 *   subtotal      2500               subtotal      2500  (net)
 *   delivery       475               VAT            526
 *   -----------------                delivery       475
 *   total         2975               -----------------
 *   of which VAT   434               total         3501
 * ```
 *
 * Under `Inclusive` the tax is a figure to *report*: the total is what it
 * always was, and the VAT is shown beneath it. Under `Exclusive` it is an
 * amount to *charge*, and it lands in the total.
 *
 * Two lines and not one, because the figures differ and it is the per-line
 * ones that are right. 21% of 2500 in a single sum is 525, but each line is
 * printed and checked on its own, so each rounds on its own: 263 twice is 526.
 * See the rounding rule in {@see Money}.
 */
enum PriceConvention: string
{
    /**
     * Prices already contain their tax. The Belgian consumer norm.
     */
    case Inclusive = 'inclusive';

    /**
     * Prices are net, and tax is added on top. The business-to-business norm.
     */
    case Exclusive = 'exclusive';

    /**
     * The tax on an amount quoted under this convention, in cents.
     *
     * The one place the two conventions differ arithmetically, so the only
     * place either formula appears. Everything else asks this and does not
     * care which answer it is getting.
     *
     * @param int $amount The amount as the shop quotes it, in cents.
     * @param int|float $percentage The rate, as a percentage: 21 means 21%.
     * @return int The tax, in cents.
     */
    public function taxOn(int $amount, int|float $percentage): int
    {
        return match ($this) {
            self::Inclusive => Money::percentageIn($amount, $percentage),
            self::Exclusive => Money::percentageOf($amount, $percentage),
        };
    }

    /**
     * Whether tax computed under this convention belongs in the total.
     *
     * True only for {@see self::Exclusive}. Under `Inclusive` the tax is
     * already inside every amount the total is built from, so adding it again
     * would charge it twice.
     *
     * @return bool
     */
    public function addsTaxToTheTotal(): bool
    {
        return $this === self::Exclusive;
    }
}

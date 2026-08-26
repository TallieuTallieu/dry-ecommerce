<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Tax;

use Tnt\Ecommerce\Money;

/**
 * Whether the prices a shop quotes have tax in them — the single fact this
 * package cannot infer. Set in `ecommerce.prices`; each order records the
 * convention it was placed under. See docs/tax.md.
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
     * The tax on an amount quoted under this convention, in cents — the only
     * place either formula appears.
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
     * Whether tax computed under this convention belongs in the total — true
     * only for {@see self::Exclusive}.
     *
     * @return bool
     */
    public function addsTaxToTheTotal(): bool
    {
        return $this === self::Exclusive;
    }
}

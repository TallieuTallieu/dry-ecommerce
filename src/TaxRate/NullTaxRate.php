<?php

namespace Tnt\Ecommerce\TaxRate;

use Tnt\Ecommerce\Contracts\TaxRateInterface;

/**
 * A tax rate that taxes nothing.
 *
 * The rounding rule never comes into play here: zero percent of any amount is
 * zero cents exactly.
 */
class NullTaxRate implements TaxRateInterface
{
    /**
     * @param int $amount
     * @return int
     */
    public function getTax(int $amount): int
    {
        return 0;
    }
}

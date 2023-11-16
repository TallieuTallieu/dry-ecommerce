<?php

namespace Tnt\Ecommerce\TaxRate;

use Tnt\Ecommerce\Contracts\TaxRateInterface;

class NullTaxRate implements TaxRateInterface
{
    public function getTax(float $amount): float
    {
        return 0;
    }
}
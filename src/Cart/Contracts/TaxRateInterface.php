<?php

namespace Tnt\Ecommerce\Contracts;

interface TaxRateInterface
{
    /**
     * @param float $amount
     * @return float
     */
    public function getTax(float $amount): float;
}
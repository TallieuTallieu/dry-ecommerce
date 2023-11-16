<?php

namespace Tnt\Ecommerce\Contracts;

interface TotalingInterface
{
    /**
     * @return float
     */
    public function getSubTotal(): float;

    /**
     * @return float
     */
    public function getTotal(): float;

    /**
     * @return float
     */
    public function getReduction(): float;
}
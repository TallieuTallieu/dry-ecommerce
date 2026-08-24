<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Contracts\TaxableInterface;
use Tnt\Ecommerce\Contracts\TaxRateInterface;

/**
 * A buyable that carries tax and has no stock.
 *
 * One of the four capability combinations. Everything but the rate comes from
 * {@see FakeBuyable}, so what this class is for is visible in the one method it
 * adds.
 */
final class FakeTaxableBuyable extends FakeBuyable implements TaxableInterface
{
    /**
     * @param string $id
     * @param int $price The unit price, in cents.
     * @param TaxRateInterface $taxRate
     * @param string $title
     */
    public function __construct(
        string $id,
        int $price,
        private readonly TaxRateInterface $taxRate,
        string $title = 'A taxed thing'
    ) {
        parent::__construct($id, $price, $title);
    }

    public function getTaxRate(): TaxRateInterface
    {
        return $this->taxRate;
    }
}

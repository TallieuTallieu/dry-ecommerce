<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Contracts\StockWorkerInterface;
use Tnt\Ecommerce\Contracts\TaxableInterface;
use Tnt\Ecommerce\Contracts\TaxRateInterface;

/**
 * A buyable that is both counted and taxed — the fat old contract, opted into.
 *
 * The fourth capability combination, and the one that shows the two are
 * independent: a shop that wants what `BuyableInterface` used to demand says so
 * in the `implements` clause and gets exactly that, with nothing null standing
 * in for anything.
 */
final class FakeStockedTaxableBuyable extends FakeStockedBuyable implements
    TaxableInterface
{
    /**
     * @param string $id
     * @param int $price The unit price, in cents.
     * @param StockWorkerInterface $stockWorker
     * @param TaxRateInterface $taxRate
     * @param string $title
     */
    public function __construct(
        string $id,
        int $price,
        StockWorkerInterface $stockWorker,
        private readonly TaxRateInterface $taxRate,
        string $title = 'A counted, taxed thing'
    ) {
        parent::__construct($id, $price, $stockWorker, $title);
    }

    public function getTaxRate(): TaxRateInterface
    {
        return $this->taxRate;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Contracts\BuyableInterface;
use Tnt\Ecommerce\Contracts\StockWorkerInterface;
use Tnt\Ecommerce\Contracts\TaxRateInterface;
use Tnt\Ecommerce\Stock\NullStockWorker;
use Tnt\Ecommerce\TaxRate\NullTaxRate;

/**
 * Something sellable that is not a database row.
 *
 * `BuyableInterface`'s shape is deliberately not touched by this ticket, so
 * this implements it as it stands today, fat contract and all. Only the price
 * changed: it is integer cents now, so `1225` is €12.25.
 */
final class FakeBuyable implements BuyableInterface
{
    /**
     * @param string $id
     * @param int $price The unit price, in cents.
     * @param string $title
     */
    public function __construct(
        private readonly string $id,
        private readonly int $price,
        private readonly string $title = 'A thing'
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return 'Description of ' . $this->title;
    }

    public function getPrice(): int
    {
        return $this->price;
    }

    public function getThumbnailSource(): string
    {
        return '';
    }

    public function getStockWorker(): StockWorkerInterface
    {
        return new NullStockWorker();
    }

    public function getTaxRate(): TaxRateInterface
    {
        return new NullTaxRate();
    }
}

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
 * `BuyableInterface` is deliberately not touched by this ticket, so this
 * implements it as it stands today, fat contract and all.
 */
final class FakeBuyable implements BuyableInterface
{
    public function __construct(
        private readonly string $id,
        private readonly float $price,
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

    public function getPrice(): float
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

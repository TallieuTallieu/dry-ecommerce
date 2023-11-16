<?php

namespace Tnt\Ecommerce\Contracts;

/**
 * Interface BuyableInterface
 * @package Tnt\Ecommerce\Contracts
 */
interface BuyableInterface
{
    /**
     * @return string
     */
    public function getId(): string;

    /**
     * @return string
     */
    public function getTitle(): string;

    /**
     * @return string
     */
    public function getDescription(): string;

    /**
     * @return float
     */
    public function getPrice(): float;

    /**
     * @return string
     */
    public function getThumbnailSource(): string;

    /**
     * @return StockWorkerInterface
     */
    public function getStockWorker(): StockWorkerInterface;

    /**
     * @return TaxRateInterface
     */
    public function getTaxRate(): TaxRateInterface;
}
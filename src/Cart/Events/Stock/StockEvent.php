<?php

namespace Tnt\Ecommerce\Events\Stock;

use Oak\Dispatcher\Event;
use Tnt\Ecommerce\Contracts\BuyableInterface;
use Tnt\Ecommerce\Contracts\StockWorkerInterface;

abstract class StockEvent extends Event
{
	/**
	 * @var $quantity
	 */
	private $quantity;

	/**
	 * @var StockWorkerInterface $stockWorker
	 */
	private $stockWorker;

	/**
	 * @var BuyableInterface $buyable
	 */
	private $buyable;

	/**
	 * Incremented constructor.
	 * @param StockWorkerInterface $stockWorker
	 * @param BuyableInterface $buyable
	 * @param int $quantity
	 */
	public function __construct(StockWorkerInterface $stockWorker, BuyableInterface $buyable, int $quantity)
	{
		$this->stockWorker = $stockWorker;
		$this->buyable = $buyable;
		$this->quantity = $quantity;
	}

	/**
	 * @return StockWorkerInterface
	 */
	public function getStockWorker(): StockWorkerInterface
	{
		return $this->stockWorker;
	}

	/**
	 * @return BuyableInterface
	 */
	public function getBuyable(): BuyableInterface
	{
		return $this->buyable;
	}

	/**
	 * @return int
	 */
	public function getQuantity(): int
	{
		return $this->quantity;
	}
}
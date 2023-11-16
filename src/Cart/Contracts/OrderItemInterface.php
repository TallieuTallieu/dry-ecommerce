<?php

namespace Tnt\Ecommerce\Contracts;

/**
 * Interface OrderItemInterface
 * @package Tnt\Ecommerce\Contracts
 */
interface OrderItemInterface
{
	/**
	 * @return int
	 */
	public function getQuantity(): int;

	/**
	 * @return BuyableInterface
	 */
	public function getBuyable(): BuyableInterface;
}
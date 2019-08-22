<?php

namespace Tnt\Ecommerce\Fulfillment\Method;

use Tnt\Ecommerce\Contracts\CartInterface;
use Tnt\Ecommerce\Contracts\FulfillmentMethodInterface;
use Tnt\Ecommerce\Fulfillment\HasFulfillmentAttributes;

class DigitalDelivery implements FulfillmentMethodInterface
{
	use HasFulfillmentAttributes;

	public function getCost(CartInterface $cart): float
	{
		return 50;
	}

	public function getTitle(): string
	{
		return 'Digitaal in je mailbox';
	}

	public function getId(): int
	{
		return 5;
	}
}
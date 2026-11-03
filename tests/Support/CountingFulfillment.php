<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Contracts\AttributeStorageAwareInterface;
use Tnt\Ecommerce\Contracts\CartInterface;
use Tnt\Ecommerce\Contracts\FulfillmentInterface;
use Tnt\Ecommerce\Fulfillment\HasFulfillmentAttributes;

/**
 * A flat-cost fulfillment that counts how often it is asked its cost — what
 * shows that a reduction that does not reach delivery never asks.
 */
final class CountingFulfillment implements
    FulfillmentInterface,
    AttributeStorageAwareInterface
{
    use HasFulfillmentAttributes;

    public int $asked = 0;

    public function __construct(private readonly int $cost) {}

    public function getId(): string
    {
        return 'counting';
    }

    public function getCost(CartInterface $cart): int
    {
        $this->asked++;

        return $this->cost;
    }

    public function getTitle(): string
    {
        return 'Counting fulfillment';
    }

    /**
     * @return array<int, string>
     */
    public function requireAttributes(): array
    {
        return [];
    }
}

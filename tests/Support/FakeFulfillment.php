<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Contracts\AttributeStorageAwareInterface;
use Tnt\Ecommerce\Contracts\CartInterface;
use Tnt\Ecommerce\Contracts\FulfillmentInterface;
use Tnt\Ecommerce\Fulfillment\HasFulfillmentAttributes;

/**
 * A fulfillment method with a flat cost, and one required attribute so that the
 * attribute seam gets exercised too.
 */
final class FakeFulfillment implements
    FulfillmentInterface,
    AttributeStorageAwareInterface
{
    use HasFulfillmentAttributes;

    /**
     * @param string|int $id
     * @param float $cost
     * @param array<int, string> $required
     */
    public function __construct(
        private readonly string|int $id,
        private readonly float $cost,
        private readonly array $required = []
    ) {}

    public function getId(): string|int
    {
        return $this->id;
    }

    public function getCost(CartInterface $cart): float
    {
        return $this->cost;
    }

    public function getTitle(): string
    {
        return 'Fake fulfillment';
    }

    /**
     * @return array<int, string>
     */
    public function requireAttributes(): array
    {
        return $this->required;
    }
}

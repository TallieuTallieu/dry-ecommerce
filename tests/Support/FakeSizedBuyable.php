<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Contracts\PricedByOptionsInterface;

/**
 * A buyable whose `size` option sets the unit price: a size in the table
 * costs what it says, anything else — no size, an unknown one — the base
 * price.
 */
class FakeSizedBuyable extends FakeBuyable implements PricedByOptionsInterface
{
    /**
     * @param string $id
     * @param int $price The base unit price, in cents.
     * @param array<string, int> $sizes Size => unit price, in cents.
     */
    public function __construct(
        string $id,
        int $price,
        private readonly array $sizes
    ) {
        parent::__construct($id, $price, 'A sized thing');
    }

    public function getPriceFor(array $options): int
    {
        $size = $options['size'] ?? null;

        return is_string($size) && isset($this->sizes[$size])
            ? $this->sizes[$size]
            : $this->getPrice();
    }
}

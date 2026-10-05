<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Contracts\HasVariantsInterface;
use Tnt\Ecommerce\Contracts\VariantInterface;

/**
 * A buyable sold as one of the variants it was built with, in that order.
 * {@see withdraw()} takes one off offer, the way a shop hides a variant
 * while a line of it sits in a basket.
 */
class FakeVariantBuyable extends FakeBuyable implements HasVariantsInterface
{
    /** @var array<string, VariantInterface> */
    private array $variants = [];

    /**
     * @param string $id
     * @param int $price The base unit price, in cents.
     * @param array<int, VariantInterface> $variants
     */
    public function __construct(string $id, int $price, array $variants)
    {
        parent::__construct($id, $price, 'A thing in variants');

        foreach ($variants as $variant) {
            $this->variants[$variant->getId()] = $variant;
        }
    }

    public function getVariants(): iterable
    {
        return array_values($this->variants);
    }

    public function getVariant(string $id): ?VariantInterface
    {
        return $this->variants[$id] ?? null;
    }

    public function withdraw(string $id): void
    {
        unset($this->variants[$id]);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Contracts\VariantInterface;

/**
 * A variant as a shop might keep one: an id, a title, and a price of its own
 * or none. Prices are integer cents.
 */
final class FakeVariant implements VariantInterface
{
    public function __construct(
        private readonly string $id,
        private readonly string $title,
        private readonly ?int $price = null
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getPrice(): ?int
    {
        return $this->price;
    }
}

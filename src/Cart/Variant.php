<?php

namespace Tnt\Ecommerce\Cart;

use Tnt\Ecommerce\Contracts\VariantInterface;

/**
 * A variant as an order line sold it: the id, the title and the unit price,
 * kept whatever happens to the shop's own variant afterwards.
 */
final class Variant implements VariantInterface
{
    /**
     * @param string $id
     * @param string $title
     * @param int|null $price The unit price charged, in cents
     */
    public function __construct(
        private readonly string $id,
        private readonly string $title,
        private readonly ?int $price
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

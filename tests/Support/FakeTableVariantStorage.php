<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Cart\Variant;
use Tnt\Ecommerce\Contracts\OrderItemInterface;
use Tnt\Ecommerce\Contracts\VariantInterface;
use Tnt\Ecommerce\Contracts\VariantStorageInterface;
use Tnt\Ecommerce\Model\OrderItem;

/**
 * A storage that keeps the frozen variant beside the order line rather than
 * in it — a shop's own `order_item_variant` table, kept in memory and keyed
 * on the line object, since an in-memory line has no id.
 */
final class FakeTableVariantStorage implements VariantStorageInterface
{
    /** @var array<int, Variant> */
    public array $rows = [];

    public function freeze(
        OrderItem $line,
        VariantInterface $variant,
        int $unitPrice
    ): void {
        $this->rows[spl_object_id($line)] = new Variant(
            $variant->getId(),
            $variant->getTitle(),
            $unitPrice
        );
    }

    public function frozenOf(OrderItemInterface $line): ?VariantInterface
    {
        return $this->rows[spl_object_id($line)] ?? null;
    }
}

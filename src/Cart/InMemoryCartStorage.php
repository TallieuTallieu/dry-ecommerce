<?php

namespace Tnt\Ecommerce\Cart;

use Tnt\Ecommerce\Contracts\BuyableInterface;
use Tnt\Ecommerce\Contracts\CartItemInterface;
use Tnt\Ecommerce\Contracts\CartStorageInterface;
use Tnt\Ecommerce\Model\DiscountCode;

/**
 * A cart storage that keeps everything in an array — shipped so a project can
 * exercise its checkout code without a session or a database. Per-instance and
 * forgetful; not a substitute for {@see SessionCartStorage} in a running shop.
 */
class InMemoryCartStorage implements CartStorageInterface
{
    /**
     * Lines, keyed by buyable plus canonical options — the same merge key the
     * session-backed storage queries on.
     *
     * @var array<string, InMemoryCartItem>
     */
    private array $items = [];

    /**
     * Stands in for the row table's auto-increment. Never reset and never
     * reused, not even by {@see clear()}.
     */
    private int $nextId = 1;

    private string|int|null $fulfillmentId = null;

    private ?DiscountCode $discount = null;

    private ?int $orderId = null;

    /**
     * @var array<string, mixed>
     */
    private array $fulfillmentAttributes = [];

    /**
     * What separates the parent from the options in a merge key.
     *
     * A raw NUL byte, because the options half is canonical JSON and
     * `json_encode()` writes a NUL as `\u0000` — so no options string can
     * contain this, and no line can be mistaken for a line under a parent.
     */
    private const PARENT_SEPARATOR = "\0parent:";

    /**
     * The merge key: which buyable, with what selection, under which line.
     *
     * @param BuyableInterface $buyable
     * @param array<array-key, mixed> $options
     * @param CartItemInterface|null $parent
     * @return string
     */
    private function key(
        BuyableInterface $buyable,
        array $options,
        ?CartItemInterface $parent = null
    ): string {
        // The parent goes last: {@see variantsOf()} matches on the buyable
        // prefix, and anything in front of it would hide the line from stock
        // counting and whole-buyable removal.
        return $this->variantPrefix($buyable) .
            (LineOptions::canonical($options) ?? '') .
            ($parent === null ? '' : self::PARENT_SEPARATOR . $parent->getId());
    }

    /**
     * The part of the merge key that names the buyable alone. The trailing
     * separator is load-bearing — without it id `1` would prefix id `10`.
     *
     * @param BuyableInterface $buyable
     * @return string
     */
    private function variantPrefix(BuyableInterface $buyable): string
    {
        return get_class($buyable) . ':' . $buyable->getId() . ':';
    }

    /**
     * @return array<int, CartItemInterface>
     */
    public function items(): array
    {
        return array_values($this->items);
    }

    /**
     * @param BuyableInterface $buyable
     * @param int $quantity
     * @param array<array-key, mixed> $options
     * @param CartItemInterface|null $parent
     * @return void
     */
    public function add(
        BuyableInterface $buyable,
        int $quantity = 1,
        array $options = [],
        ?CartItemInterface $parent = null
    ): void {
        $key = $this->key($buyable, $options, $parent);

        if (isset($this->items[$key])) {
            $item = $this->items[$key];
            $item->setQuantity($item->getQuantity() + $quantity);

            return;
        }

        $item = new InMemoryCartItem(
            (string) $this->nextId++,
            $buyable,
            $quantity,
            $options
        );

        $item->setParent($parent);

        $this->items[$key] = $item;
    }

    /**
     * @param CartItemInterface $parent
     * @return array<int, CartItemInterface>
     */
    public function childrenOf(CartItemInterface $parent): array
    {
        $children = [];

        foreach ($this->items as $item) {
            if ($item->getParent()?->getId() === $parent->getId()) {
                $children[] = $item;
            }
        }

        return $children;
    }

    /**
     * The sum across the buyable's option-variants — stock counts the
     * buyable, not the selection.
     *
     * @param BuyableInterface $buyable
     * @return int
     */
    public function quantityOf(BuyableInterface $buyable): int
    {
        $quantity = 0;

        foreach ($this->variantsOf($buyable) as $item) {
            $quantity += $item->getQuantity();
        }

        return $quantity;
    }

    /**
     * Every variant, because a caller holding only a buyable cannot name one.
     *
     * @param BuyableInterface $buyable
     * @return void
     */
    public function remove(BuyableInterface $buyable): void
    {
        foreach (array_keys($this->variantsOf($buyable)) as $key) {
            $this->forgetLine($key);
        }
    }

    /**
     * @param string $itemId
     * @param int $quantity
     * @return void
     */
    public function updateQuantity(string $itemId, int $quantity): void
    {
        $key = $this->keyOfLine($itemId);

        if ($key === null) {
            return;
        }

        if ($quantity <= 0) {
            $this->forgetLine($key);

            return;
        }

        $this->items[$key]->setQuantity($quantity);
    }

    /**
     * @param string $itemId
     * @return void
     */
    public function removeItem(string $itemId): void
    {
        $key = $this->keyOfLine($itemId);

        if ($key !== null) {
            $this->forgetLine($key);
        }
    }

    /**
     * Drop a line and everything hanging off it — the same rule the
     * row-backed storage spells out in `deleteLine()`.
     *
     * @param string $key
     * @return void
     */
    private function forgetLine(string $key): void
    {
        $item = $this->items[$key] ?? null;

        if ($item === null) {
            return;
        }

        unset($this->items[$key]);

        foreach ($this->childrenOf($item) as $child) {
            $childKey = $this->keyOfLine($child->getId());

            if ($childKey !== null) {
                $this->forgetLine($childKey);
            }
        }
    }

    /**
     * The lines holding one buyable, whatever their options, keyed as
     * {@see $items} keys them.
     *
     * @param BuyableInterface $buyable
     * @return array<string, InMemoryCartItem>
     */
    private function variantsOf(BuyableInterface $buyable): array
    {
        $prefix = $this->variantPrefix($buyable);

        return array_filter(
            $this->items,
            static fn(string $key): bool => str_starts_with($key, $prefix),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * The merge key of the line an id names, or null when no line has it.
     *
     * @param string $itemId
     * @return string|null
     */
    private function keyOfLine(string $itemId): ?string
    {
        foreach ($this->items as $key => $item) {
            if ($item->getId() === $itemId) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $this->items = [];
        $this->fulfillmentId = null;
        $this->discount = null;
        $this->orderId = null;
        $this->fulfillmentAttributes = [];
    }

    /**
     * @return string|int|null
     */
    public function getFulfillmentId(): string|int|null
    {
        return $this->fulfillmentId;
    }

    /**
     * @param string|int|null $id
     * @return void
     */
    public function setFulfillmentId(string|int|null $id): void
    {
        $this->fulfillmentId = $id;
    }

    /**
     * @return DiscountCode|null
     */
    public function getDiscount(): ?DiscountCode
    {
        return $this->discount;
    }

    /**
     * @param DiscountCode|null $discount
     * @return void
     */
    public function setDiscount(?DiscountCode $discount): void
    {
        $this->discount = $discount;
    }

    /**
     * @return int|null
     */
    public function getOrderId(): ?int
    {
        return $this->orderId;
    }

    /**
     * @param int|null $id
     * @return void
     */
    public function setOrderId(?int $id): void
    {
        $this->orderId = $id;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFulfillmentAttributes(): array
    {
        return $this->fulfillmentAttributes;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return void
     */
    public function setFulfillmentAttributes(array $attributes): void
    {
        $this->fulfillmentAttributes = $attributes;
    }
}

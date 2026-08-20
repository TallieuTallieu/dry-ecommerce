<?php

namespace Tnt\Ecommerce\Cart;

use Tnt\Ecommerce\Contracts\BuyableInterface;
use Tnt\Ecommerce\Contracts\CartItemInterface;
use Tnt\Ecommerce\Contracts\CartStorageInterface;
use Tnt\Ecommerce\Model\DiscountCode;

/**
 * A cart storage that keeps everything in an array.
 *
 * Shipped rather than kept in the test suite on purpose: it is how a project
 * exercises its own checkout, coupon and fulfillment code without a session or
 * a database, and it is the thing that proves
 * {@see CartStorageInterface} is a real seam and not decoration.
 *
 * It is per-instance and forgets everything when the request ends, so it is not
 * a substitute for {@see SessionCartStorage} in a running shop.
 */
class InMemoryCartStorage implements CartStorageInterface
{
    /**
     * Lines, keyed by buyable so that adding the same buyable twice merges.
     *
     * @var array<string, InMemoryCartItem>
     */
    private array $items = [];

    private string|int|null $fulfillmentId = null;

    private ?DiscountCode $discount = null;

    /**
     * @param BuyableInterface $buyable
     * @return string
     */
    private function key(BuyableInterface $buyable): string
    {
        return get_class($buyable) . ':' . $buyable->getId();
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
     * @return void
     */
    public function add(BuyableInterface $buyable, int $quantity = 1): void
    {
        $key = $this->key($buyable);

        if (isset($this->items[$key])) {
            $item = $this->items[$key];
            $item->setQuantity($item->getQuantity() + $quantity);

            return;
        }

        $this->items[$key] = new InMemoryCartItem($buyable, $quantity);
    }

    /**
     * @param BuyableInterface $buyable
     * @return void
     */
    public function remove(BuyableInterface $buyable): void
    {
        unset($this->items[$this->key($buyable)]);
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $this->items = [];
        $this->fulfillmentId = null;
        $this->discount = null;
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
}

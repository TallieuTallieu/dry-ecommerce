<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Contracts\CartItemInterface;
use Tnt\Ecommerce\Model\Order;

/**
 * An in-memory order whose {@see Order::isRePlaceable()} answer can be forced.
 *
 * Exists to pin that `Cart::place()` READS the method rather than re-deriving
 * the rule: forcing the answer against what the state and payment status say
 * must flip what place() does. `null` leaves the real answer standing.
 */
final class ForcedRePlaceabilityOrder extends Order
{
    /**
     * The forced answer, or null for the real one.
     *
     * @var bool|null
     */
    public ?bool $rePlaceable = null;

    /**
     * The lines added, kept off the database like {@see InMemoryOrder}.
     *
     * @var array<int, CartItemInterface>
     */
    public array $lines = [];

    /**
     * @return bool
     */
    public function isRePlaceable(): bool
    {
        return $this->rePlaceable ?? parent::isRePlaceable();
    }

    /**
     * @return void
     */
    public function save()
    {
        if ($this->id === null) {
            $this->id = 1;
        }
    }

    /**
     * @param CartItemInterface $cartItem
     * @return void
     */
    public function add(CartItemInterface $cartItem)
    {
        $this->lines[] = $cartItem;
    }

    /**
     * @return void
     */
    public function clearItems(): void
    {
        $this->lines = [];
    }
}

<?php

namespace Tnt\Ecommerce\Contracts;

/**
 * One line of a placed order. Everything here reads the line's own frozen
 * copy; {@see getBuyable()} is the one exception — it loads the live model.
 */
interface OrderItemInterface
{
    /**
     * @return int
     */
    public function getQuantity(): int;

    /**
     * @return BuyableInterface
     */
    public function getBuyable(): BuyableInterface;

    /**
     * The line total frozen at checkout, in cents — repricing the product
     * does not restate the invoice.
     *
     * @see \Tnt\Ecommerce\Money
     * @return int
     */
    public function getPrice(): int;

    /**
     * The choices the line was placed with — the order's own frozen copy, or
     * [] when there were none.
     *
     * @return array<array-key, mixed>
     */
    public function getOptions(): array;
}

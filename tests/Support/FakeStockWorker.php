<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Contracts\BuyableInterface;
use Tnt\Ecommerce\Contracts\StockWorkerInterface;
use Tnt\Ecommerce\Stock\StockWouldGoNegative;

/**
 * A stock that counts in an array.
 *
 * The shipped {@see \Tnt\Ecommerce\Stock\StockWorker} reads `ecommerce_stock`
 * and `ecommerce_stock_item`, so it cannot appear in tests/Unit at all. This
 * keeps the same behaviour the cart depends on — a count per buyable, and
 * "never stocked" meaning unavailable rather than unlimited — with nothing
 * behind it.
 *
 * It mirrors the shipped worker's negative-stock policy too, down to the
 * exception and the default, because a fake that is laxer than the real thing
 * is how a test suite comes to prove something the package does not do. What it
 * cannot mirror is the database, so what the shipped worker does with a real
 * row is not covered here — see the note at the top of StockTest.
 *
 * Quantities are `int`, as they now are throughout: the column always was one.
 */
final class FakeStockWorker implements StockWorkerInterface
{
    /**
     * @param bool $allowNegative As on {@see \Tnt\Ecommerce\Stock\StockWorker}.
     */
    public function __construct(private readonly bool $allowNegative = false) {}

    /**
     * Counts, keyed the way a stock line is: class name plus buyable id.
     *
     * @var array<string, int>
     */
    private array $quantities = [];

    /**
     * @param BuyableInterface $buyable
     * @return string
     */
    private function key(BuyableInterface $buyable): string
    {
        return get_class($buyable) . ':' . $buyable->getId();
    }

    public function isAvailable(
        BuyableInterface $buyable,
        int $quantity = 1
    ): bool {
        return $this->getQuantity($buyable) >= $quantity;
    }

    public function increment(
        BuyableInterface $buyable,
        int $quantity = 1
    ): void {
        $this->quantities[$this->key($buyable)] =
            $this->getQuantity($buyable) + $quantity;
    }

    /**
     * @throws StockWouldGoNegative
     */
    public function decrement(
        BuyableInterface $buyable,
        int $quantity = 1
    ): void {
        $key = $this->key($buyable);

        // Never stocked here means there is no line to take out of, matching
        // the shipped worker's early return rather than creating one at -1.
        if (!isset($this->quantities[$key])) {
            return;
        }

        $remaining = $this->quantities[$key] - $quantity;

        if ($remaining < 0 && !$this->allowNegative) {
            throw new StockWouldGoNegative(
                $buyable,
                $this->quantities[$key],
                $quantity
            );
        }

        $this->quantities[$key] = $remaining;
    }

    public function getQuantity(BuyableInterface $buyable): int
    {
        return $this->quantities[$this->key($buyable)] ?? 0;
    }
}

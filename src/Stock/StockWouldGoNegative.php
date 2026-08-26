<?php

namespace Tnt\Ecommerce\Stock;

use RuntimeException;
use Tnt\Ecommerce\Contracts\BuyableInterface;

/**
 * A decrement that would take a stock below zero, in a stock that does not
 * allow it (`allowNegative` on {@see StockWorker}). Nothing in the package
 * decrements — this fires wherever the shop does. See docs/stock.md.
 */
final class StockWouldGoNegative extends RuntimeException
{
    /**
     * @param BuyableInterface $buyable The buyable being taken out.
     * @param int $available How many the stock held.
     * @param int $requested How many were asked for.
     */
    public function __construct(
        private readonly BuyableInterface $buyable,
        private readonly int $available,
        private readonly int $requested
    ) {
        parent::__construct(
            sprintf(
                'Cannot take %d of %s#%s out of a stock holding %d: it would ' .
                    'leave %d. Check availability first with ' .
                    'Cart::canAdd(), or build the worker with ' .
                    'allowNegative: true if this shop backorders.',
                $requested,
                get_class($buyable),
                $buyable->getId(),
                $available,
                $available - $requested
            )
        );
    }

    /**
     * The buyable that was being taken out.
     */
    public function getBuyable(): BuyableInterface
    {
        return $this->buyable;
    }

    /**
     * How many the stock held.
     */
    public function getAvailable(): int
    {
        return $this->available;
    }

    /**
     * How many were asked for.
     */
    public function getRequested(): int
    {
        return $this->requested;
    }

    /**
     * How many more were asked for than there were — always positive.
     */
    public function getShortfall(): int
    {
        return $this->requested - $this->available;
    }
}

<?php

namespace Tnt\Ecommerce\Stock;

use RuntimeException;
use Tnt\Ecommerce\Contracts\BuyableInterface;

/**
 * A decrement that would take a stock below zero, in a stock that does not
 * allow it.
 *
 * Whether it is allowed is the shop's answer, given once when it builds the
 * worker: `new StockWorker('warehouse')` refuses, and
 * `new StockWorker('warehouse', allowNegative: true)` lets the count go
 * negative. Both are legitimate. A shop that backorders wants the negative — it
 * is how many it owes — and a shop that does not wants to hear about the
 * oversell at the moment it happens rather than from a stock report a week
 * later. What the package will not do is pick one and be quietly wrong for the
 * other, which is why nothing is clamped and nothing is guessed.
 *
 * # Where it is likely to be thrown from
 *
 * Nothing in this package decrements anything. A shop does, and the usual place
 * is a listener on {@see \Tnt\Ecommerce\Events\Order\Paid} — which is to say
 * *after* the money has been taken. Catching it there and reconciling by hand
 * is a real option, and so is allowing the negative on that stock and treating
 * the negative count as the reconciliation queue.
 *
 * {@see \Tnt\Ecommerce\Cart\Cart::canAdd()} is the earlier, cheaper place to
 * find out, before a sale is made rather than after.
 *
 * # Why a RuntimeException
 *
 * Unlike {@see \Tnt\Ecommerce\AmountTooLarge} and its neighbours, which extend
 * `InvalidArgumentException` because the argument itself was impossible, there
 * is nothing wrong with the quantity passed here. Taking three out is a fair
 * request; it is the stock's state at that moment that makes it impossible, and
 * that state is only knowable at runtime.
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

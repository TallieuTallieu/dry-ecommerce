<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Contracts\OrderReferenceInterface;
use Tnt\Ecommerce\Model\Order;

/**
 * A shop's own reference, as simple as one gets: numbers orders 2600001,
 * 2600002, … and counts how often it was asked.
 */
final class CountingOrderReference implements OrderReferenceInterface
{
    public int $asked = 0;

    /**
     * @param Order $order
     * @return string
     */
    public function reference(Order $order): string
    {
        $this->asked++;

        return (string) (2600000 + $this->asked);
    }
}

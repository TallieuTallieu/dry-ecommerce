<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Cart\InMemoryCartStorage;

/**
 * An in-memory cart that hands its lines over youngest first.
 *
 * Why the parent/child copy in {@see \Tnt\Ecommerce\Cart\Cart::place()} is a
 * second pass and not part of `Order::add()`: nothing says a parent is handed
 * over before the line hanging off it. A storage keyed on a merge key answers
 * in whatever order its array holds, and a one-pass copy would find no parent
 * row to point at. This one guarantees the awkward order rather than hoping
 * for it.
 */
final class ChildFirstCartStorage extends InMemoryCartStorage
{
    /**
     * @return array<int, \Tnt\Ecommerce\Contracts\CartItemInterface>
     */
    public function items(): array
    {
        return array_reverse(parent::items());
    }
}

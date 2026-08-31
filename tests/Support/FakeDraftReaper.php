<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Model\Cart as CartModel;
use Tnt\Ecommerce\Model\Order;
use Tnt\Ecommerce\Order\DraftReaper;

/**
 * The real {@see DraftReaper} with its two queries replaced: `staleDrafts()`
 * answers from an array and remembers the cutoff it was asked for, and
 * `cartOf()` answers from whatever {@see attachCart()} put there. The
 * selections themselves are SQL and live with the repository scopes.
 */
final class FakeDraftReaper extends DraftReaper
{
    /**
     * The cutoff `reap()` computed, or null before it ran.
     */
    public ?int $askedCutoff = null;

    /**
     * @var array<int, Order>
     */
    private array $drafts;

    /**
     * @param int $lifetimeDays
     * @param array<int, Order> $drafts What the "query" will hand back.
     */
    public function __construct(int $lifetimeDays, array $drafts)
    {
        parent::__construct($lifetimeDays);

        $this->drafts = $drafts;
    }

    /**
     * The living cart behind a draft, if the test attached one — keyed per
     * draft object.
     *
     * @var array<int, CartModel>
     */
    private array $carts = [];

    /**
     * @param Order $draft
     * @param CartModel $cart
     * @return void
     */
    public function attachCart(Order $draft, CartModel $cart): void
    {
        $this->carts[spl_object_id($draft)] = $cart;
    }

    /**
     * @param int $cutoff
     * @return iterable<int, Order>
     */
    protected function staleDrafts(int $cutoff): iterable
    {
        $this->askedCutoff = $cutoff;

        return $this->drafts;
    }

    /**
     * @param Order $draft
     * @return CartModel|null
     */
    protected function cartOf(Order $draft): ?CartModel
    {
        $cart = $this->carts[spl_object_id($draft)] ?? null;

        // The same scope the production query has: notDeleted().
        if ($cart === null || $cart->deleted !== null) {
            return null;
        }

        return $cart;
    }
}

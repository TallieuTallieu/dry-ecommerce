<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Model\Order;
use Tnt\Ecommerce\Order\DraftReaper;

/**
 * The real {@see DraftReaper} with its one query replaced: `staleDrafts()`
 * answers from an array and remembers the cutoff it was asked for, so a test
 * can check both what reaping does to a draft and what "stale" was taken to
 * mean. The selection itself is SQL and lives with the repository scopes.
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
     * @param int $cutoff
     * @return iterable<int, Order>
     */
    protected function staleDrafts(int $cutoff): iterable
    {
        $this->askedCutoff = $cutoff;

        return $this->drafts;
    }
}

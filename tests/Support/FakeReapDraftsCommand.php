<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Console\ReapDraftsCommand;
use Tnt\Ecommerce\Order\DraftReaper;

/**
 * The real command with its reaper seam overridden — the refusal message,
 * the lifetime reading and the count reporting all run as themselves.
 */
final class FakeReapDraftsCommand extends ReapDraftsCommand
{
    /**
     * The reaper the command will be handed, set by the test.
     */
    public ?DraftReaper $presetReaper = null;

    /**
     * The lifetime the command asked a reaper for, or null when it refused.
     */
    public ?int $askedLifetime = null;

    /**
     * @param int $lifetimeDays
     * @return DraftReaper
     */
    protected function reaper(int $lifetimeDays): DraftReaper
    {
        $this->askedLifetime = $lifetimeDays;

        return $this->presetReaper ?? new FakeDraftReaper($lifetimeDays, []);
    }
}

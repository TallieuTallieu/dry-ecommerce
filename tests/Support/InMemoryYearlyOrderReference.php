<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Order\YearlyOrderReference;

/**
 * The real yearly reference, with the calendar and the counter table in
 * memory: the year is whatever the test sets, and each year counts from the
 * number the test seeds it with.
 */
final class InMemoryYearlyOrderReference extends YearlyOrderReference
{
    public int $currentYear = 2026;

    /**
     * The last number handed out, per year — a missing year has none yet.
     *
     * @var array<int, int>
     */
    public array $last = [];

    /**
     * @return int
     */
    protected function year(): int
    {
        return $this->currentYear;
    }

    /**
     * @param int $year
     * @return int
     */
    protected function next(int $year): int
    {
        $this->last[$year] = ($this->last[$year] ?? 0) + 1;

        return $this->last[$year];
    }
}

<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Revisions\AddOrderStateColumn;

/**
 * The real state-column revision, stopped just short of the database. Its own
 * capturing rather than {@see CapturesRevisionSql}, because this revision
 * runs two statements through two seams — the ALTER through `execute()` and
 * the backfill UPDATE through `run()` — and both are the point.
 */
final class CapturingAddOrderStateColumn extends AddOrderStateColumn
{
    /**
     * Every statement the revision would run, in order.
     *
     * @var array<int, string>
     */
    public array $statements = [];

    /**
     * @return void
     */
    protected function execute(): void
    {
        $this->queryBuilder->build();

        $this->statements[] = $this->queryBuilder->getQuery();
    }

    /**
     * @param string $sql
     * @return void
     */
    protected function run(string $sql): void
    {
        $this->statements[] = $sql;
    }
}

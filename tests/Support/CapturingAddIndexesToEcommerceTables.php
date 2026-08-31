<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Revisions\AddIndexesToEcommerceTables;

/**
 * The real indexes revision, stopped just short of the database.
 *
 * Its own `execute()` rather than {@see CapturesRevisionSql}, because the
 * revision runs one ALTER per table — five statements — and the trait's
 * single `$sql` would keep only the last of them.
 */
final class CapturingAddIndexesToEcommerceTables extends
    AddIndexesToEcommerceTables
{
    /**
     * Every statement the revision built, in order, or [] before `up()`.
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
}

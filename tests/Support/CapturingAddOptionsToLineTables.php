<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Revisions\AddOptionsToLineTables;

/**
 * The real `options` revision, stopped just short of the database.
 *
 * Its own `execute()` rather than {@see CapturesRevisionSql}, because this is
 * the one revision that runs more than one statement — an ALTER per line
 * table — and the trait's single `$sql` would keep only the last of them. The
 * whole point of capturing this revision is that *both* tables get the
 * column, so both statements are kept, in the order they would run.
 */
final class CapturingAddOptionsToLineTables extends AddOptionsToLineTables
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

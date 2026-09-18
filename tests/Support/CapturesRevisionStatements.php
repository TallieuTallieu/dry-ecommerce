<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Keeps every statement a revision builds, instead of running them.
 *
 * {@see CapturesRevisionSql} with one `$sql` is enough for a revision that
 * runs a single statement. A revision that alters two tables runs two, and the
 * point of capturing those is that BOTH tables are reached — so they are kept
 * in the order they would run.
 */
trait CapturesRevisionStatements
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

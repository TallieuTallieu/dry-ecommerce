<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Keeps a revision's SQL instead of running it.
 *
 * `DatabaseRevision::execute()` builds the query and then hands it to
 * `dry\db\Connection`, which cannot even be autoloaded outside a booted Dry
 * application. Everything before that hand-off is pure string building, so
 * overriding the last step is enough to read what a revision would have run
 * with no database anywhere near the test.
 *
 * A trait rather than a base class, because each revision has to be subclassed
 * on its own to keep the `up()` under test.
 */
trait CapturesRevisionSql
{
    /**
     * The statement the revision built, or '' before `up()` is called.
     *
     * @var string
     */
    public string $sql = '';

    /**
     * @return void
     */
    protected function execute(): void
    {
        $this->queryBuilder->build();

        $this->sql = $this->queryBuilder->getQuery();
    }
}

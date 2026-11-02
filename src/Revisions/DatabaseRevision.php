<?php

namespace Tnt\Ecommerce\Revisions;

use dry\db\Connection;
use Tnt\Dbi\QueryBuilder;

/**
 * Class DatabaseRevision
 * @package Tnt\Ecommerce
 */
abstract class DatabaseRevision
{
    /**
     * @var QueryBuilder $queryBuilder
     */
    protected $queryBuilder;

    /**
     * DatabaseRevision constructor.
     * @param QueryBuilder $queryBuilder
     */
    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    /**
     * @return void
     */
    protected function execute(): void
    {
        $this->queryBuilder->build();
        Connection::get()->query($this->queryBuilder->getQuery());
    }

    /**
     * One raw statement — the builder speaks DDL and SELECT, not UPDATE. A
     * seam like {@see execute()}, for the same reason: a test double keeps
     * the statement instead of running it.
     *
     * @param string $sql
     * @return void
     */
    protected function run(string $sql): void
    {
        Connection::get()->query($sql);
    }
}

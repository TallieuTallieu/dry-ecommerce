<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Revisions\AddOrderStateColumn;

/**
 * The real state-column revision, stopped just short of the database: the
 * ALTER through `execute()` and the backfill UPDATE through `run()`, both
 * kept in the order they would run.
 */
final class CapturingAddOrderStateColumn extends AddOrderStateColumn
{
    use CapturesRevisionStatements;
}

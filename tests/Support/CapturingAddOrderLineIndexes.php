<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Revisions\AddOrderLineIndexes;

/**
 * The real revision, stopped just short of the database.
 *
 * @see CapturesRevisionStatements
 */
final class CapturingAddOrderLineIndexes extends AddOrderLineIndexes
{
    use CapturesRevisionStatements;
}

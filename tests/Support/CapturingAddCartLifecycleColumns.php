<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Revisions\AddCartLifecycleColumns;

/**
 * The real cart-lifecycle revision, stopped just short of the database.
 */
final class CapturingAddCartLifecycleColumns extends AddCartLifecycleColumns
{
    use CapturesRevisionSql;
}

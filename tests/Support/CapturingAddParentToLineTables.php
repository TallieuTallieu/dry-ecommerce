<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Revisions\AddParentToLineTables;

/**
 * The real revision, stopped just short of the database.
 *
 * @see CapturesRevisionStatements
 */
final class CapturingAddParentToLineTables extends AddParentToLineTables
{
    use CapturesRevisionStatements;
}

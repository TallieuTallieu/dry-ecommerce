<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Revisions\AddVariantToLineTables;

/**
 * The real revision, stopped just short of the database.
 *
 * @see CapturesRevisionStatements
 */
final class CapturingAddVariantToLineTables extends AddVariantToLineTables
{
    use CapturesRevisionStatements;
}

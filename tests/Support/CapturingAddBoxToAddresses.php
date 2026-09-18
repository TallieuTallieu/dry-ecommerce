<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Revisions\AddBoxToAddresses;

/**
 * The real revision, stopped just short of the database.
 *
 * @see CapturesRevisionStatements
 */
final class CapturingAddBoxToAddresses extends AddBoxToAddresses
{
    use CapturesRevisionStatements;
}

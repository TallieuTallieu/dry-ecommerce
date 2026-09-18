<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Contracts;

/**
 * Names the admin portal holding the read-only payment ledger. Bound to a
 * `dry\admin\Portal`; a project opts in by adding it to `admin\Router::$modules`.
 * See docs/admin.md.
 */
interface LedgerPortalInterface {}

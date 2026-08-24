<?php

declare(strict_types=1);

namespace Tests\Support;

use Oak\Container\Container;

/**
 * A container that says it is not running in a console.
 *
 * `Container::isRunningInConsole()` is `php_sapi_name() === 'cli'`, and the test
 * runner is the CLI, so a test booting a service provider gets the console
 * branch whether it wants it or not. `EcommerceServiceProvider::boot()` puts
 * its migrator registration behind exactly that check, which would drag a
 * Migrator and a MigrationManager into a test that only wants the event
 * listeners on the other side of it.
 *
 * Overriding the one method is enough, and it is honest rather than a dodge: a
 * shop serving a checkout over HTTP genuinely is not running in a console, so
 * this is the branch the code under test takes in production.
 */
final class WebContainer extends Container
{
    /**
     * @return bool
     */
    public function isRunningInConsole(): bool
    {
        return false;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Support;

use dry\db\DuplicateEntryException;
use Tnt\Ecommerce\Model\Customer;

/**
 * A customer whose table is an array, for running {@see Customer::forUser()}
 * with the container stopped.
 *
 * `forUser()` touches the database twice — the `findByUser()` seam and
 * `save()` — and both are overridden here to keep to `$rows`. The race is
 * simulated where it actually happens: a save that arrives second plants the
 * winner's row and throws the same {@see DuplicateEntryException} the unique
 * index would, so the catch-and-reread path runs for real.
 */
final class ForUserCustomer extends Customer
{
    /**
     * The "table": rows keyed by user id.
     *
     * @var array<int, Customer>
     */
    public static array $rows = [];

    /**
     * The rival row that wins the insert race, or null for no race. Consumed
     * by the first save(), which plants it and throws.
     *
     * @var Customer|null
     */
    public static ?Customer $loseRaceTo = null;

    /**
     * How many times save() ran (including the one that threw).
     *
     * @var int
     */
    public static int $saveCalls = 0;

    /**
     * Throw the duplicate without planting a row — the winner deleted again
     * before the re-read, so forUser() has nothing to hand back.
     *
     * @var bool
     */
    public static bool $winnerVanishes = false;

    /**
     * Back to an empty table — static state has to be scrubbed per test.
     *
     * @return void
     */
    public static function reset(): void
    {
        static::$rows = [];
        static::$loseRaceTo = null;
        static::$saveCalls = 0;
        static::$winnerVanishes = false;
    }

    /**
     * @param int $userId
     * @return Customer|null
     */
    protected static function findByUser(int $userId): ?Customer
    {
        return static::$rows[$userId] ?? null;
    }

    /**
     * @return void
     */
    public function save()
    {
        static::$saveCalls++;

        $userId = $this->getUserId();

        if ($userId === null) {
            return;
        }

        if (static::$winnerVanishes) {
            throw new DuplicateEntryException('uq_user');
        }

        if (static::$loseRaceTo !== null) {
            // The other request's INSERT landed first: its row is in the
            // table, ours bounces off the unique index.
            static::$rows[$userId] = static::$loseRaceTo;
            static::$loseRaceTo = null;

            throw new DuplicateEntryException('uq_user');
        }

        static::$rows[$userId] = $this;
    }
}

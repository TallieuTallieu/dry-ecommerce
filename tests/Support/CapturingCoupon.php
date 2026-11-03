<?php

declare(strict_types=1);

namespace Tests\Support;

use dry\db\DuplicateEntryException;
use Tnt\Ecommerce\Model\Coupon;

/**
 * The real coupon with the connection taken out: every statement redeem()
 * would send is kept, with its parameters, and answered from a script.
 */
final class CapturingCoupon extends Coupon
{
    /**
     * @var list<array{string, list<int>}>
     */
    public array $statements = [];

    /**
     * Rows each UPDATE reports changed.
     */
    public int $affectedRows = 1;

    /**
     * What `SELECT LAST_INSERT_ID()` answers.
     */
    public int $lastInsertId = 0;

    /**
     * Whether the claim's INSERT hits UNIQUE(order).
     */
    public bool $alreadyClaimed = false;

    protected function now(): int
    {
        return 1781524800;
    }

    protected function insert(string $sql, int ...$params): int
    {
        $this->statements[] = [$sql, array_values($params)];

        if ($this->alreadyClaimed) {
            throw new DuplicateEntryException('uq_order', $sql, $params);
        }

        return 41;
    }

    protected function affected(string $sql, int ...$params): int
    {
        $this->statements[] = [$sql, array_values($params)];

        return $this->affectedRows;
    }

    protected function field(string $sql): mixed
    {
        $this->statements[] = [$sql, []];

        return (string) $this->lastInsertId;
    }

    /**
     * The statements alone, in order.
     *
     * @return list<string>
     */
    public function sql(): array
    {
        return array_map(
            static fn(array $statement): string => $statement[0],
            $this->statements
        );
    }
}

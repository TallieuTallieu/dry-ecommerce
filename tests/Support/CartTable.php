<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * A stand-in for `ecommerce_cart`: rows by id, shared between storage
 * instances the way the real table is shared between requests. What makes the
 * cookie round-trip testable — two {@see InMemoryCookieCartStorage} instances
 * over one table are two requests over one database.
 */
final class CartTable
{
    /**
     * @var array<int, InMemoryCart>
     */
    public array $rows = [];

    /**
     * Stands in for the auto-increment. Never reset and never reused.
     */
    private int $nextId = 1;

    /**
     * @param InMemoryCart $cart
     * @return int The id the "insert" handed back.
     */
    public function insert(InMemoryCart $cart): int
    {
        $id = $this->nextId++;
        $this->rows[$id] = $cart;

        return $id;
    }

    /**
     * The row a token names, deleted or not — the SQL scope is the
     * repository's; that a soft-deleted row reads as absent is
     * DatabaseCartStorage's own check, and returning the row here is what
     * exercises it.
     *
     * @param string $token
     * @return InMemoryCart|null
     */
    public function byToken(string $token): ?InMemoryCart
    {
        foreach ($this->rows as $row) {
            if ($row->token === $token) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param int $id
     * @return void
     */
    public function remove(int $id): void
    {
        unset($this->rows[$id]);
    }

    /**
     * The one row the table holds — for the tests that created exactly one
     * cart and want to lay hands on it. Loud otherwise: which row was meant
     * is then a mistake in the test.
     *
     * @return InMemoryCart
     */
    public function only(): InMemoryCart
    {
        if (count($this->rows) !== 1) {
            throw new \LogicException(
                sprintf(
                    'Expected exactly one cart row, found %d.',
                    count($this->rows)
                )
            );
        }

        $row = reset($this->rows);
        assert($row instanceof InMemoryCart);

        return $row;
    }
}

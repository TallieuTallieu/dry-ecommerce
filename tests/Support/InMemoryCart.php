<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Model\Cart as CartModel;

/**
 * A cart row that keeps to memory — the {@see \Tests\Support\InMemoryOrder}
 * pattern applied to `ecommerce_cart`. `save()` registers the row in a
 * {@see CartTable} instead of a table; `delete()` removes it there instead of
 * cascading through line rows.
 */
final class InMemoryCart extends CartModel
{
    /**
     * How many times `save()` was called.
     */
    public int $saveCount = 0;

    /**
     * Whether `delete()` — the hard delete `clear()` does — happened.
     */
    public bool $hardDeleted = false;

    private ?CartTable $table;

    /**
     * @param CartTable|null $table Where the row "inserts" itself, when the
     *                              test wants it findable again.
     */
    public function __construct(?CartTable $table = null)
    {
        parent::__construct();

        $this->table = $table;
    }

    /**
     * @return mixed|void
     */
    public function save()
    {
        $this->saveCount++;

        if ($this->id === null) {
            $this->id = $this->table !== null ? $this->table->insert($this) : 1;
        }
    }

    /**
     * @return void
     */
    public function delete()
    {
        $this->hardDeleted = true;

        if ($this->table !== null && $this->id !== null) {
            $this->table->remove((int) $this->id);
        }
    }
}

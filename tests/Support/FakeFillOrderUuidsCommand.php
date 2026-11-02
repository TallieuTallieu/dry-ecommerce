<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Console\FillOrderUuidsCommand;

/**
 * The real command with its two queries in memory: `$uuids` stands in for the
 * column, keyed by order id, null where an order has none yet.
 */
final class FakeFillOrderUuidsCommand extends FillOrderUuidsCommand
{
    /**
     * @var array<int, string|null>
     */
    public array $uuids = [];

    /**
     * Orders that get a uuid from elsewhere between the select and the write
     * — a placement running beside the command.
     *
     * @var array<int, string>
     */
    public array $savedMeanwhile = [];

    /**
     * @return array<int, int>
     */
    protected function orderIdsWithoutUuid(): array
    {
        $ids = array_keys(
            array_filter($this->uuids, fn(?string $uuid) => $uuid === null)
        );

        foreach ($this->savedMeanwhile as $id => $uuid) {
            $this->uuids[$id] = $uuid;
        }

        return $ids;
    }

    /**
     * @param int $id
     * @param string $uuid
     * @return int
     */
    protected function assign(int $id, string $uuid): int
    {
        if (($this->uuids[$id] ?? null) !== null) {
            return 0;
        }

        $this->uuids[$id] = $uuid;

        return 1;
    }
}

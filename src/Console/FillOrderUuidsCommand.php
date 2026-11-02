<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Console;

use dry\db\Connection;
use Oak\Console\Command\Command;
use Oak\Console\Command\Signature;
use Oak\Contracts\Console\InputInterface;
use Oak\Contracts\Console\OutputInterface;
use Tnt\Ecommerce\Order\OrderUuid;

/**
 * `php oak ecommerce:fill-order-uuids` — give every order without a `uuid`
 * one. Run it once after migrating: the revision adds the column empty, and
 * only orders saved since have one. Safe to run again; it only reaches rows
 * still without. See docs/orders.md#the-uuid.
 */
class FillOrderUuidsCommand extends Command
{
    protected function createSignature(Signature $signature): Signature
    {
        return $signature
            ->setName('ecommerce:fill-order-uuids')
            ->setDescription('Give every order without a uuid one');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $filled = 0;

        // The same generator a new order gets its uuid from. Not UUID(),
        // which is a version 1 — the MAC address and the time, the opposite
        // of unguessable.
        foreach ($this->orderIdsWithoutUuid() as $id) {
            $filled += $this->assign($id, OrderUuid::generate());
        }

        $output->writeLine(
            sprintf(
                'Gave %d order%s a uuid.',
                $filled,
                $filled === 1 ? '' : 's'
            ),
            OutputInterface::TYPE_INFO
        );
    }

    /**
     * The orders still without a uuid — a test seam.
     *
     * @return array<int, int>
     */
    protected function orderIdsWithoutUuid(): array
    {
        $ids = [];

        foreach (
            Connection::get()->select_query(
                'SELECT `id` FROM `ecommerce_order` WHERE `uuid` IS NULL'
            )
            as $row
        ) {
            /** @var array<string, mixed> $row */
            if (is_numeric($row['id'] ?? null)) {
                $ids[] = (int) $row['id'];
            }
        }

        return $ids;
    }

    /**
     * Write one uuid, unless the order got one in the meantime — a placement
     * running beside the command saves its own. Answers 1 when it wrote, 0
     * when it did not. A test seam.
     *
     * @param int $id
     * @param string $uuid
     * @return int
     */
    protected function assign(int $id, string $uuid): int
    {
        return Connection::get()->update_query(
            'UPDATE `ecommerce_order` SET `uuid` = ? WHERE `id` = ? AND `uuid` IS NULL',
            $uuid,
            $id
        );
    }
}

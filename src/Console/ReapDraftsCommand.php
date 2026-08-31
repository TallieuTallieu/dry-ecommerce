<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Console;

use Oak\Console\Command\Command;
use Oak\Console\Command\Signature;
use Oak\Contracts\Config\RepositoryInterface;
use Oak\Contracts\Console\InputInterface;
use Oak\Contracts\Console\OutputInterface;
use Oak\Contracts\Container\ContainerInterface;
use Tnt\Ecommerce\Cart\CartLifetime;
use Tnt\Ecommerce\Order\DraftReaper;

/**
 * `php oak ecommerce:reap-drafts` — delete draft orders not touched within
 * `ecommerce.cart_lifetime` days. Run it on a schedule; it refuses to run at
 * all when no lifetime is configured. See docs/orders.md.
 */
class ReapDraftsCommand extends Command
{
    private RepositoryInterface $config;

    /**
     * @param RepositoryInterface $config
     * @param ContainerInterface $app
     */
    public function __construct(
        RepositoryInterface $config,
        ContainerInterface $app
    ) {
        $this->config = $config;

        parent::__construct($app);
    }

    protected function createSignature(Signature $signature): Signature
    {
        return $signature
            ->setName('ecommerce:reap-drafts')
            ->setDescription(
                'Delete draft orders not touched within ' .
                    'ecommerce.cart_lifetime days'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $lifetime = CartLifetime::days($this->config);

        if ($lifetime === null) {
            // Refusing, not defaulting: any figure this command invented
            // would silently delete drafts whose carts a shop considers
            // alive. The one knob rules both.
            $output->writeLine(
                'ecommerce.cart_lifetime is not set, so there is no answer ' .
                    'to "how old is abandoned". Set it (a number of days) ' .
                    'in config/ecommerce.php; the same lifetime drives the ' .
                    'cart cookie.',
                OutputInterface::TYPE_WARNING
            );

            return;
        }

        $reaped = $this->reaper($lifetime)->reap();

        $output->writeLine(
            sprintf(
                'Reaped %d stale draft order%s (untouched for %d days or ' .
                    'more).',
                $reaped,
                $reaped === 1 ? '' : 's',
                $lifetime
            ),
            OutputInterface::TYPE_INFO
        );
    }

    /**
     * The reaper doing the actual deleting — a test seam.
     *
     * @param int $lifetimeDays
     * @return DraftReaper
     */
    protected function reaper(int $lifetimeDays): DraftReaper
    {
        return new DraftReaper($lifetimeDays);
    }
}

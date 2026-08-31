<?php

declare(strict_types=1);

/*
 * The reaper: stale drafts deleted, everything else never touched.
 *
 * A draft is a half-filled checkout form; abandoned ones would otherwise pile
 * up forever. The clock is the draft's own `updated` — touched on every
 * progressive save — measured against `ecommerce.cart_lifetime`, the same
 * knob the cookie cart lives by. The selection (state = draft, updated before
 * the cutoff) is SQL and is pinned as composed criteria in RepositoryTest;
 * what runs here is everything around it: the cutoff arithmetic, the
 * lines-first delete, the count, the config reading and the command's refusal
 * to invent a lifetime nobody configured.
 */

use Oak\Config\Repository;
use Tests\Support\FakeConsoleInput;
use Tests\Support\FakeDraftReaper;
use Tests\Support\FakeReapDraftsCommand;
use Tests\Support\InMemoryOrder;
use Tests\Support\RecordingConsoleOutput;
use Tests\Support\WebContainer;
use Tnt\Ecommerce\Cart\CartLifetime;
use Tnt\Ecommerce\Order\OrderState;

it('deletes a stale draft, lines first', function (): void {
    $draft = new InMemoryOrder();
    $draft->state = OrderState::Draft->value;

    $reaper = new FakeDraftReaper(30, [$draft]);

    expect($reaper->reap())->toBe(1);

    // Lines cleared before the row goes — a draft is not supposed to have
    // any, but one that somehow does must not leave orphans behind.
    expect($draft->clearCount)->toBe(1);
    expect($draft->deleted)->toBeTrue();
});

it('counts what it reaped', function (): void {
    $drafts = [new InMemoryOrder(), new InMemoryOrder(), new InMemoryOrder()];

    foreach ($drafts as $draft) {
        $draft->state = OrderState::Draft->value;
    }

    expect((new FakeDraftReaper(30, $drafts))->reap())->toBe(3);
});

it('measures staleness in configured days off updated', function (): void {
    $reaper = new FakeDraftReaper(30, []);
    $reaper->reap();

    $expected = time() - 30 * 86400;

    expect($reaper->askedCutoff)->toBeGreaterThanOrEqual($expected - 5);
    expect($reaper->askedCutoff)->toBeLessThanOrEqual($expected + 5);
});

it('reads the lifetime only when it is a positive int', function (): void {
    $days = fn(array $ecommerce): ?int => CartLifetime::days(
        new Repository(['ecommerce' => $ecommerce])
    );

    expect($days(['cart_lifetime' => 30]))->toBe(30);

    // Everything else is "no lifetime": the reading that keeps the
    // session-backed storage bound and the reaper refusing to run.
    expect($days([]))->toBeNull();
    expect($days(['cart_lifetime' => 0]))->toBeNull();
    expect($days(['cart_lifetime' => -3]))->toBeNull();
    expect($days(['cart_lifetime' => '30']))->toBeNull();
    expect($days(['cart_lifetime' => true]))->toBeNull();
});

/**
 * The command over one shop's config, run as `php oak ecommerce:reap-drafts`.
 *
 * @param array<string, mixed> $ecommerce
 * @return array{FakeReapDraftsCommand, RecordingConsoleOutput}
 */
function reapCommandFor(array $ecommerce): array
{
    $app = new WebContainer();
    $app->instance(Oak\Contracts\Container\ContainerInterface::class, $app);

    $command = new FakeReapDraftsCommand(
        new Repository(['ecommerce' => $ecommerce]),
        $app
    );

    return [$command, new RecordingConsoleOutput()];
}

it('refuses to reap without a configured lifetime', function (): void {
    // Refusing, not defaulting: any figure the command invented would
    // silently delete drafts whose carts the shop considers alive.
    [$command, $output] = reapCommandFor([]);

    $command->run(new FakeConsoleInput(), $output);

    expect($command->askedLifetime)->toBeNull();
    expect(implode(' ', $output->lines))->toContain('cart_lifetime');
});

it(
    'reaps with the configured lifetime and reports the count',
    function (): void {
        [$command, $output] = reapCommandFor(['cart_lifetime' => 14]);

        $stale = new InMemoryOrder();
        $stale->state = OrderState::Draft->value;
        $command->presetReaper = new FakeDraftReaper(14, [$stale]);

        $command->run(new FakeConsoleInput(), $output);

        expect($command->askedLifetime)->toBe(14);
        expect($stale->deleted)->toBeTrue();
        expect(implode(' ', $output->lines))->toContain('Reaped 1 stale draft');
    }
);

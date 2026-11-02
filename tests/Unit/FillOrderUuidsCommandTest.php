<?php

declare(strict_types=1);

use Tests\Support\FakeConsoleInput;
use Tests\Support\FakeFillOrderUuidsCommand;
use Tests\Support\RecordingConsoleOutput;
use Tests\Support\UuidV4;
use Tests\Support\WebContainer;

/**
 * @return array{FakeFillOrderUuidsCommand, RecordingConsoleOutput}
 */
function fillCommand(): array
{
    $app = new WebContainer();
    $app->instance(Oak\Contracts\Container\ContainerInterface::class, $app);

    return [new FakeFillOrderUuidsCommand($app), new RecordingConsoleOutput()];
}

it('gives every order without a uuid its own', function (): void {
    [$command, $output] = fillCommand();
    $command->uuids = [3 => null, 7 => null];

    $command->run(new FakeConsoleInput(), $output);

    expect($command->uuids[3])->toMatch(UuidV4::PATTERN);
    expect($command->uuids[7])->toMatch(UuidV4::PATTERN);
    expect($command->uuids[3])->not->toBe($command->uuids[7]);
    expect(implode(' ', $output->lines))->toContain('Gave 2 orders a uuid.');
});

it('leaves an order that already has a uuid alone', function (): void {
    // A uuid is what a URL already sent out names the order by.
    [$command, $output] = fillCommand();
    $command->uuids = [3 => 'kept', 7 => null];

    $command->run(new FakeConsoleInput(), $output);

    expect($command->uuids[3])->toBe('kept');
    expect(implode(' ', $output->lines))->toContain('Gave 1 order a uuid.');
});

it(
    'does not overwrite a uuid an order got while the command ran',
    function (): void {
        [$command, $output] = fillCommand();
        $command->uuids = [3 => null, 7 => null];
        $command->savedMeanwhile = [7 => 'from-placement'];

        $command->run(new FakeConsoleInput(), $output);

        expect($command->uuids[7])->toBe('from-placement');
        expect(implode(' ', $output->lines))->toContain('Gave 1 order a uuid.');
    }
);

it('reports nothing to do on a second run', function (): void {
    [$command, $output] = fillCommand();
    $command->uuids = [3 => null];

    $command->run(new FakeConsoleInput(), new RecordingConsoleOutput());
    $command->run(new FakeConsoleInput(), $output);

    expect(implode(' ', $output->lines))->toContain('Gave 0 orders a uuid.');
});

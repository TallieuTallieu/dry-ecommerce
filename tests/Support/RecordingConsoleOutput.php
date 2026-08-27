<?php

declare(strict_types=1);

namespace Tests\Support;

use Oak\Contracts\Console\OutputInterface;

/**
 * Console output kept as an array of lines, so a test can read what a
 * command reported.
 */
final class RecordingConsoleOutput implements OutputInterface
{
    /**
     * @var array<int, string>
     */
    public array $lines = [];

    public function writeLine(
        string $message,
        int $type = self::TYPE_PLAIN
    ): void {
        $this->lines[] = $message;
    }

    public function write(string $message, int $type = self::TYPE_PLAIN): void
    {
        $this->lines[] = $message;
    }

    public function newline(): void
    {
        //
    }
}

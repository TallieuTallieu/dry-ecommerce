<?php

declare(strict_types=1);

namespace Tests\Support;

use Oak\Console\Command\Signature;
use Oak\Contracts\Console\InputInterface;

/**
 * A console invocation with nothing on it: no options, no arguments, no
 * subcommand — `php oak ecommerce:reap-drafts` and enter.
 */
final class FakeConsoleInput implements InputInterface
{
    private ?Signature $signature = null;

    public function setSignature(Signature $signature): void
    {
        $this->signature = $signature;
    }

    public function getSignature(): Signature
    {
        if ($this->signature === null) {
            throw new \LogicException('No signature was set on this input.');
        }

        return $this->signature;
    }

    public function validate(): void {}

    /**
     * @return array<string, mixed>
     */
    public function getArguments(): array
    {
        return [];
    }

    public function getArgument(string $name): mixed
    {
        return null;
    }

    public function hasArgument(string $name): bool
    {
        return false;
    }

    public function setArgument(string $name, mixed $value): void {}

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return [];
    }

    public function getOption(string $name): mixed
    {
        return null;
    }

    public function setOption(string $name, mixed $value): void {}

    public function hasSubCommand(): bool
    {
        return false;
    }

    public function getSubCommand(): string
    {
        return '';
    }

    public function setSubCommand(string $name): void {}
}

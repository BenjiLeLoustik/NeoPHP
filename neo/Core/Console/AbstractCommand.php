<?php

namespace Neo\Core\Console;

use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Interface\CommandInterface;

class AbstractCommand implements CommandInterface
{

    /**
     * @return list<string>
     */
    protected function getAvailableProjects(): array
    {
        $srcDir = ROOT_DIR . '/src/';

        if (!is_dir($srcDir)) {
            return [];
        }

        return array_map(
            fn(string $dir) => basename($dir),
            glob($srcDir . '*', GLOB_ONLYDIR) ?: []
        );
    }

    /**
     * @param array<int|string, mixed> $args
     */
    public function execute(array $args): void
    {}

    public function getName(): string
    {
        $attr = new \ReflectionClass($this)
            ->getAttributes(Command::class)[0] ?? null;

        return $attr?->newInstance()->name ?? '';
    }

    public function getDescription(): string
    {
        $attr = new \ReflectionClass($this)
            ->getAttributes(Command::class)[0] ?? null;

        return $attr?->newInstance()->description ?? '';
    }

    public function getHelp(): string
    {
        return '';
    }
}
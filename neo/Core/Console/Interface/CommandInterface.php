<?php
declare(strict_types=1);

namespace Neo\Core\Console\Interface;

interface CommandInterface
{
    /**
     * @param array<int|string, mixed> $args
     */
    public function execute(array $args): void;

    public function getName(): string;

    public function getDescription(): string;

    public function getHelp(): string;
}

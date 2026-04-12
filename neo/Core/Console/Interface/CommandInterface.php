<?php
declare(strict_types=1);

namespace Neo\Core\Console\Interface;

interface CommandInterface
{
    public function execute(array $args): void;

    public function getName(): string;

    public function getDescription(): string;
}

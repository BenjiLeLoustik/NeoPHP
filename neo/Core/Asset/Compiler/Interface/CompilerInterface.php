<?php
declare(strict_types=1);

namespace Neo\Core\Asset\Compiler\Interface;

interface CompilerInterface
{
    public function compile(string $source, string $target): void;
}

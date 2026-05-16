<?php
declare(strict_types=1);

namespace Neo\Core\Profiler\Collector;

interface CollectorInterface
{
    public function getName(): string;
    public function collect(): array;
}
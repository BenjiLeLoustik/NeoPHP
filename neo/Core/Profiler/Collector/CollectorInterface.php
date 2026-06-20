<?php
declare(strict_types=1);

namespace Neo\Core\Profiler\Collector;

interface CollectorInterface
{
    public function getName(): string;

    /**
     * @return array<string, mixed>
     */
    public function collect(): array;

    /** @param array<string, mixed> $data */
    public function renderTab(array $data): string;

    /** @param array<string, mixed> $data */
    public function renderPanel(array $data): string;
}
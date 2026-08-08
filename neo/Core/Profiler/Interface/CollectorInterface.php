<?php

declare(strict_types=1);

namespace Neo\Core\Profiler\Interface;

interface CollectorInterface
{
    public function getName(): string;

    /**
     * @return array<string, mixed>
     */
    public function collect(): array;

    public function inToolbar(): bool;

    public function inProfiler(): bool;

    /**
     * @return array{label: string, value: string, badge: string|null}
     */
    public function toolbarData(): array;

    /**
     * @return array{title: string, badge: string|null, blocks: list<array<string, mixed>>}
     */
    public function profilerData(): array;
}
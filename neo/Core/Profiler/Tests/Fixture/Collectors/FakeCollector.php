<?php
declare(strict_types=1);

namespace Neo\Core\Profiler\Tests\Fixture\Collectors;

use Neo\Core\Profiler\Interface\CollectorInterface;

class FakeCollector implements CollectorInterface
{
    public function getName(): string
    {
        return 'fake';
    }

    public function collect(): array
    {
        return ['value' => 42];
    }

    public function renderTab(array $data): string
    {
        return '<div class="n-tab-fake">' . $data['value'] . '</div>';
    }

    public function renderPanel(array $data): string
    {
        return '<div class="n-panel-fake">' . $data['value'] . '</div>';
    }
}
<?php

declare(strict_types=1);

namespace Neo\Core\Tools\Debug\Collector;

use Neo\Core\Tools\Debug\DumpRecorder;
use Neo\Core\Profiler\Interface\CollectorInterface;

final class DumpsCollector implements CollectorInterface
{
    public function getName(): string
    {
        return 'dumps';
    }

    public function collect(): array
    {
        return ['dumps' => DumpRecorder::getDumps()];
    }

    public function inToolbar(): bool
    {
        return false;
    }

    public function inProfiler(): bool
    {
        return true;
    }

    public function toolbarData(): array
    {
        $data = $this->collect();
        $count = count($data['dumps']);

        return [
            'label' => 'Dumps',
            'value' => '',
            'badge' => null,
        ];
    }

    public function profilerData(): array
    {
        $data = $this->collect();

        if ($data['dumps'] === []) {
            return [
                'title' => 'Dumps',
                'badge' => null,
                'blocks' => [
                    [
                        'type' => 'kv',
                        'section' => null,
                        'rows' => [
                            ['label' => 'Status', 'value' => 'No dump() call during this request.'],
                        ],
                    ],
                ],
            ];
        }

        $tabs = [];

        foreach ($data['dumps'] as $i => $dump) {
            $tabs[] = [
                'label' => $this->shortLabel($dump['caller'], $i),
                'badge' => null,
                'badgeType' => 'neutral',
                'blocks' => [
                    [
                        'type' => 'raw-html',
                        'section' => $dump['caller'],
                        'html' => $dump['html'],
                    ],
                ],
            ];
        }

        return [
            'title' => 'Dumps',
            'badge' => (string) count($data['dumps']),
            'badgeType' => 'neutral',
            'metrics' => [
                ['label' => 'Total dumps', 'value' => (string) count($data['dumps'])],
            ],
            'blocks' => [
                ['type' => 'tabs', 'section' => null, 'tabs' => $tabs],
            ],
        ];
    }

    private function shortLabel(?string $caller, int $index): string
    {
        if ($caller === null) {
            return '#' . ($index + 1);
        }

        $lastColon = strrpos($caller, ':');

        if ($lastColon === false) {
            return '#' . ($index + 1) . ' ' . basename($caller);
        }

        $path = substr($caller, 0, $lastColon);
        $line = substr($caller, $lastColon + 1);

        return '#' . ($index + 1) . ' ' . basename($path) . ':' . $line;
    }
}
<?php

declare(strict_types=1);

namespace Neo\Core\Profiler\Collector;

use Neo\Core\Profiler\Interface\CollectorInterface;
use Neo\Core\Profiler\ProfilerManager;
use Neo\Core\Profiler\TimelineRecorder;

final class TimelineCollector implements CollectorInterface
{
    public function __construct(private readonly ProfilerManager $profiler)
    {
    }

    public function getName(): string
    {
        return 'timeline';
    }

    public function collect(): array
    {
        return [
            'entries' => TimelineRecorder::getEntries(),
            'totalDuration' => $this->profiler->getTotalDuration(),
        ];
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
        return [
            'label' => 'Timeline',
            'value' => '',
            'badge' => null,
        ];
    }

    public function profilerData(): array
    {
        $data = $this->collect();

        if ($data['entries'] === []) {
            return [
                'title' => 'Timeline',
                'badge' => null,
                'blocks' => [
                    [
                        'type' => 'kv',
                        'section' => null,
                        'rows' => [
                            ['label' => 'Status', 'value' => 'No timed event was recorded during this request.'],
                        ],
                    ],
                ],
            ];
        }

        return [
            'title' => 'Timeline',
            'badge' => null,
            'metrics' => [
                ['label' => 'Total duration', 'value' => (string) $data['totalDuration'], 'unit' => 'ms'],
                ['label' => 'Events', 'value' => (string) count($data['entries'])],
            ],
            'blocks' => [
                [
                    'type' => 'timeline',
                    'section' => null,
                    'totalDuration' => $data['totalDuration'],
                    'entries' => $data['entries'],
                ],
            ],
        ];
    }
}
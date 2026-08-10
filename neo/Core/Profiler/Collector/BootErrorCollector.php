<?php

declare(strict_types=1);

namespace Neo\Core\Profiler\Collector;

use Neo\Core\Profiler\Interface\CollectorInterface;
use Neo\Core\Profiler\ProfilerManager;

final class BootErrorCollector implements CollectorInterface
{
    public function __construct(
        private ProfilerManager $profiler
    ) {
    }

    public function getName(): string
    {
        return 'boot-error';
    }

    public function collect(): array
    {
        $error = $this->profiler->getBootError();

        if ($error === null) {
            return ['hasError' => false];
        }

        return [
            'hasError' => true,
            'class' => $error::class,
            'message' => $error->getMessage(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'trace' => $error->getTraceAsString(),
        ];
    }

    public function inToolbar(): bool
    {
        return $this->profiler->hasBootError();
    }

    public function inProfiler(): bool
    {
        return $this->profiler->hasBootError();
    }

    public function toolbarData(): array
    {
        $data = $this->collect();

        return [
            'label' => 'Boot',
            'value' => $data['hasError'] ? 'Failed' : 'OK',
            'badge' => $data['hasError'] ? '!' : null,
            'badgeType' => 'alert',
        ];
    }

    public function profilerData(): array
    {
        $data = $this->collect();

        if (!$data['hasError']) {
            return [
                'title' => 'Boot Error',
                'badge' => null,
                'blocks' => [],
            ];
        }

        $traceRows = explode("\n", $data['trace'])
                |> (fn($x) => array_filter($x, static fn(string $line) => trim($line) !== ''))
                |> array_values(...);

        return [
            'title' => 'Boot Error',
            'badge' => '!',
            'badgeType' => 'alert',
            'blocks' => [
                [
                    'type' => 'kv',
                    'section' => null,
                    'rows' => [
                        ['label' => 'Exception', 'value' => $data['class']],
                        ['label' => 'Message', 'value' => $data['message']],
                        ['label' => 'File', 'value' => $data['file'] . ':' . $data['line']],
                    ],
                ],
                [
                    'type' => 'table',
                    'section' => 'Stack trace',
                    'columns' => ['Frame'],
                    'rows' => array_map(static fn (string $line) => [$line], $traceRows),
                ],
            ],
        ];
    }
}
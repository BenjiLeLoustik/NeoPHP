<?php

declare(strict_types=1);

namespace Neo\Core\Validator\Collector;

use Neo\Core\DI\Container;
use Neo\Core\Profiler\Interface\CollectorInterface;
use Neo\Core\Validator\ValidatorManager;

final class ValidatorCollector implements CollectorInterface
{
    public function __construct(private readonly Container $container)
    {
    }

    public function getName(): string
    {
        return 'validator';
    }

    public function collect(): array
    {
        /** @var ValidatorManager $validator */
        $validator = $this->container->get(ValidatorManager::class);
        $log = $validator->getValidationLog();

        $failedCount = count(array_filter($log, static fn (array $l) => !$l['passed']));

        return [
            'total' => count($log),
            'failedCount' => $failedCount,
            'checks' => $log,
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
        return [];
    }

    public function profilerData(): array
    {
        $data = $this->collect();

        if ($data['total'] === 0) {
            return [
                'title' => 'Validator',
                'badge' => null,
                'metrics' => [
                    ['label' => 'Checks run', 'value' => '0'],
                ],
                'blocks' => [
                    [
                        'type' => 'kv',
                        'section' => null,
                        'rows' => [
                            ['label' => 'Status', 'value' => 'No validation was run during this request.'],
                        ],
                    ],
                ],
            ];
        }

        return [
            'title' => 'Validator',
            'badge' => $data['failedCount'] > 0 ? (string) $data['failedCount'] : null,
            'badgeType' => 'alert',
            'metrics' => [
                ['label' => 'Checks run', 'value' => (string) $data['total']],
                ['label' => 'Failed', 'value' => (string) $data['failedCount']],
            ],
            'blocks' => [
                [
                    'type' => 'table',
                    'section' => null,
                    'columns' => ['Model', 'Field', 'Constraint', 'Value', 'Result'],
                    'rows' => array_map(
                        static fn (array $c) => [
                            $c['model'],
                            $c['field'],
                            $c['constraint'],
                            $c['value'],
                            $c['passed'] ? 'Passed' : 'Failed: ' . $c['message'],
                        ],
                        $data['checks']
                    ),
                ],
            ],
        ];
    }
}
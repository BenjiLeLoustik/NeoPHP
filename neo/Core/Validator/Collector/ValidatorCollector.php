<?php

declare(strict_types=1);

namespace Neo\Core\Validator\Collector;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Profiler\Interface\CollectorInterface;
use Neo\Core\Tools\Debug\Dumper;
use Neo\Core\Validator\ValidatorManager;

final class ValidatorCollector implements CollectorInterface
{
    public function __construct(
        private Container $container
    ) {
    }

    public function getName(): string
    {
        return 'validator';
    }

    /**
     * @throws \ReflectionException
     * @throws ContainerException
     */
    public function collect(): array
    {
        /** @var ValidatorManager $validator */
        $validator = $this->container->get(ValidatorManager::class);
        $log = $validator->getValidationLog();

        $failedCount = $log
                |> (fn (array $l): array => array_filter($l, static fn (array $x) => !$x['passed']))
                |> count(...);

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
        return [
            'label' => 'Validator',
            'value' => '',
            'badge' => null,
        ];
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
                    'type' => 'log-list',
                    'section' => null,
                    'messageLabel' => 'Field',
                    'rows' => array_map(
                        fn (array $c) => [
                            'time' => $this->shortClass($c['constraint']),
                            'channel' => $this->shortClass($c['model']),
                            'origin' => $c['passed'] ? 'PASS' : 'FAIL',
                            'message' => $c['field'],
                            'context' => $this->formatContext($c),
                        ],
                        $data['checks']
                    ),
                ],
            ],
        ];
    }

    /**
     * @param array{model: class-string, field: string, constraint: class-string, value: string, passed: bool, message: string|null} $c
     * @return array{raw: true, html: string}
     */
    private function formatContext(array $c): array
    {
        $html = new Dumper()->render([[
            'model' => $c['model'],
            'field' => $c['field'],
            'constraint' => $c['constraint'],
            'value' => $c['value'],
            'result' => $c['passed'] ? 'Passed' : 'Failed',
            'message' => $c['message'],
        ]]);

        return ['raw' => true, 'html' => $html];
    }

    private function shortClass(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }
}
<?php

declare(strict_types=1);

namespace Neo\Core\Translation\Collector;

use Neo\Core\Profiler\Interface\CollectorInterface;
use Neo\Core\Translation\TranslationManager;

final class TranslationCollector implements CollectorInterface
{
    public function __construct(
        private TranslationManager $translator
    ) {
    }

    public function getName(): string
    {
        return 'translation';
    }

    public function collect(): array
    {
        $records = $this->translator->getRecords();
        $byDomain = [];

        foreach ($records as $record) {
            $byDomain[$record['domain']][] = $record;
        }

        $missingCount = $records
                |> (fn (array $r): array => array_filter($r, static fn (array $x) => !$x['found']))
                |> count(...);

        return [
            'enabled' => $this->translator->isEnabledTranslation(),
            'locale' => $this->translator->getLocale(),
            'total' => count($records),
            'missingCount' => $missingCount,
            'byDomain' => $byDomain,
        ];
    }

    public function inToolbar(): bool
    {
        return true;
    }

    public function inProfiler(): bool
    {
        return true;
    }

    public function toolbarData(): array
    {
        $data = $this->collect();

        return [
            'label' => 'Locale',
            'value' => $data['enabled'] ? strtoupper($data['locale']) : 'Disabled',
            'badge' => $data['missingCount'] > 0 ? (string) $data['missingCount'] : null,
            'badgeType' => 'alert',
        ];
    }

    public function profilerData(): array
    {
        $data = $this->collect();

        if (!$data['enabled']) {
            return [
                'title' => 'Translation',
                'badge' => null,
                'blocks' => [
                    [
                        'type' => 'kv',
                        'section' => null,
                        'rows' => [
                            ['label' => 'Status', 'value' => 'Translation is disabled in app.config.php'],
                        ],
                    ],
                ],
            ];
        }

        if ($data['total'] === 0) {
            return [
                'title' => 'Translation',
                'badge' => null,
                'metrics' => [
                    ['label' => 'Locale', 'value' => strtoupper($data['locale'])],
                    ['label' => 'Translated', 'value' => '0'],
                ],
                'blocks' => [
                    [
                        'type' => 'kv',
                        'section' => null,
                        'rows' => [
                            ['label' => 'Status', 'value' => 'No translation was resolved during this request.'],
                        ],
                    ],
                ],
            ];
        }

        $tabs = [];

        foreach ($data['byDomain'] as $domain => $records) {
            $missingInDomain = $records
                    |> (fn (array $r): array => array_filter($r, static fn (array $x) => !$x['found']))
                    |> count(...);

            $tabs[] = [
                'label' => $domain,
                'badge' => $missingInDomain > 0 ? (string) $missingInDomain : null,
                'badgeType' => 'alert',
                'blocks' => [
                    [
                        'type' => 'table',
                        'section' => null,
                        'columns' => ['Status', 'Key', 'Result'],
                        'rows' => array_map(
                            static fn (array $r) => [
                                $r['found'] ? 'Found' : 'Missing',
                                $r['key'],
                                $r['result'],
                            ],
                            $records
                        ),
                    ],
                ],
            ];
        }

        return [
            'title' => 'Translation',
            'badge' => $data['missingCount'] > 0 ? (string) $data['missingCount'] : null,
            'badgeType' => 'alert',
            'metrics' => [
                ['label' => 'Locale', 'value' => strtoupper($data['locale'])],
                ['label' => 'Translated', 'value' => (string) $data['total']],
                ['label' => 'Missing', 'value' => (string) $data['missingCount']],
            ],
            'blocks' => [
                ['type' => 'tabs', 'section' => null, 'tabs' => $tabs],
            ],
        ];
    }
}
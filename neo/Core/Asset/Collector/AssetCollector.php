<?php

declare(strict_types=1);

namespace Neo\Core\Asset\Collector;

use Neo\Core\Asset\AssetManager;
use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Profiler\Interface\CollectorInterface;

final class AssetCollector implements CollectorInterface
{
    public function __construct(private readonly Container $container)
    {
    }

    public function getName(): string
    {
        return 'assets';
    }

    /**
     * @throws ContainerException
     */
    public function collect(): array
    {
        /** @var AssetManager $assets */
        $assets = $this->container->get(AssetManager::class);
        $log = $assets->getAssetLog();

        $compiledCount = $log
                |> (fn (array $l): array => array_filter($l, static fn (array $a) => $a['compiled']))
                |> count(...);

        return [
            'total' => count($log),
            'compiledCount' => $compiledCount,
            'assets' => $log,
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
            'label' => 'Assets',
            'value' => '',
            'badge' => null,
        ];
    }

    public function profilerData(): array
    {
        $data = $this->collect();

        if ($data['total'] === 0) {
            return [
                'title' => 'Assets',
                'badge' => null,
                'metrics' => [
                    ['label' => 'Resolved', 'value' => '0'],
                ],
                'blocks' => [
                    [
                        'type' => 'kv',
                        'section' => null,
                        'rows' => [
                            ['label' => 'Status', 'value' => 'No asset was resolved during this request.'],
                        ],
                    ],
                ],
            ];
        }

        return [
            'title' => 'Assets',
            'badge' => $data['compiledCount'] > 0 ? (string) $data['compiledCount'] : null,
            'metrics' => [
                ['label' => 'Resolved', 'value' => (string) $data['total']],
                ['label' => 'Compiled', 'value' => (string) $data['compiledCount']],
            ],
            'blocks' => [
                [
                    'type' => 'table',
                    'section' => null,
                    'columns' => ['Source', 'Resolved path', 'Compiled', 'Duration'],
                    'rows' => array_map(
                        static fn (array $a) => [
                            $a['path'],
                            $a['resolvedPath'],
                            $a['compiled'] ? 'Yes' : 'No (cached)',
                            $a['duration'] . ' ms',
                        ],
                        $data['assets']
                    ),
                ],
            ],
        ];
    }
}
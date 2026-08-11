<?php

declare(strict_types=1);

namespace Neo\Core\Database\Collector;

use Neo\Core\Database\Access\Connection\DatabaseConnection;
use Neo\Core\DI\Container;
use Neo\Core\Profiler\Interface\CollectorInterface;

final class DatabaseConnectionCollector implements CollectorInterface
{
    public function __construct(
        private Container $container
    ) {
    }

    public function getName(): string
    {
        return 'connections';
    }

    public function collect(): array
    {
        $enabled = false;
        $configuredConnections = [];

        try {
            $config = $this->container->get('database.configModule')->from('database');
            $enabled = (bool) ($config->get('enabled') ?? false);
            $configuredConnections = $config->get('connections') ?? [];
        } catch (\Throwable) {
            // Config not available — treat as disabled.
        }

        $activeNames = DatabaseConnection::getConnectionNames();
        $defaultName = DatabaseConnection::getDefaultName();

        $connections = [];

        foreach ($configuredConnections as $name => $cfg) {
            $connections[] = [
                'name' => $name,
                'isDefault' => $name === $defaultName,
                'isActive' => in_array($name, $activeNames, true),
                'driver' => $cfg['driver'] ?? 'n/a',
                'host' => $cfg['host'] ?? 'n/a',
                'port' => $cfg['port'] ?? 'n/a',
                'dbname' => $cfg['dbname'] ?? 'n/a',
                'charset' => $cfg['charset'] ?? 'n/a',
                'prefix' => $cfg['prefix'] ?? '',
                'persistent' => (bool) ($cfg['persistent'] ?? false),
            ];
        }

        return [
            'enabled' => $enabled,
            'defaultName' => $defaultName,
            'activeCount' => count($activeNames),
            'connections' => $connections,
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
            'label' => 'Connections',
            'value' => '',
            'badge' => null,
        ];
    }

    public function profilerData(): array
    {
        $data = $this->collect();

        if (!$data['enabled']) {
            return [
                'title' => 'Connection',
                'group' => 'Database',
                'badge' => null,
                'blocks' => [
                    [
                        'type' => 'kv',
                        'section' => null,
                        'rows' => [
                            ['label' => 'Status', 'value' => 'Database is disabled in database.config.php'],
                        ],
                    ],
                ],
            ];
        }

        return [
            'title' => 'Connection',
            'group' => 'Database',
            'badge' => null,
            'metrics' => [
                ['label' => 'Default', 'value' => $data['defaultName'] ?? 'n/a'],
                ['label' => 'Active connections', 'value' => (string) $data['activeCount']],
                ['label' => 'Configured', 'value' => (string) count($data['connections'])],
            ],
            'blocks' => [
                [
                    'type' => 'table',
                    'section' => null,
                    'columns' => ['Name', 'Status', 'Driver', 'Host', 'Port', 'Database', 'Charset', 'Persistent'],
                    'rows' => array_map(
                        static fn (array $c) => [
                            $c['name'] . ($c['isDefault'] ? ' (default)' : ''),
                            $c['isActive'] ? 'Connected' : 'Not connected',
                            $c['driver'],
                            $c['host'],
                            (string) $c['port'],
                            $c['dbname'],
                            $c['charset'],
                            $c['persistent'] ? 'Yes' : 'No',
                        ],
                        $data['connections']
                    ),
                ],
            ],
        ];
    }
}
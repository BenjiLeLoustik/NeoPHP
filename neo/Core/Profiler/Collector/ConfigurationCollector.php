<?php

declare(strict_types=1);

namespace Neo\Core\Profiler\Collector;

use Neo\Core\DI\Container;
use Neo\Core\Profiler\Interface\CollectorInterface;

final class ConfigurationCollector implements CollectorInterface
{
    private const string FRAMEWORK_PACKAGE_NAME = 'neo/core';

    public function __construct(private readonly Container $container)
    {
    }

    public function getName(): string
    {
        return 'configuration';
    }

    public function collect(): array
    {
        return [
            'frameworkVersion' => $this->resolveFrameworkVersion(),
            'phpVersion' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'os' => PHP_OS_FAMILY,
            'environment' => $this->resolveEnvironment(),
            'timezone' => date_default_timezone_get(),
            'memoryLimit' => ini_get('memory_limit') ?: 'n/a',
            'maxExecutionTime' => ini_get('max_execution_time') ?: 'n/a',
            'uploadMaxFilesize' => ini_get('upload_max_filesize') ?: 'n/a',
            'postMaxSize' => ini_get('post_max_size') ?: 'n/a',
            'opcacheEnabled' => function_exists('opcache_get_status') && (opcache_get_status(false) !== false),
            'composerPackages' => $this->composerPackages(),
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
            'label' => 'PHP',
            'value' => $data['phpVersion'],
            'badge' => null,
        ];
    }

    public function profilerData(): array
    {
        $data = $this->collect();

        return [
            'title' => 'Configuration',
            'badge' => null,
            'metrics' => [
                ['label' => 'Framework', 'value' => $data['frameworkVersion']],
                ['label' => 'PHP', 'value' => $data['phpVersion']],
                ['label' => 'Environment', 'value' => strtoupper($data['environment'])],
            ],
            'blocks' => [
                [
                    'type' => 'kv',
                    'section' => 'Framework & runtime',
                    'rows' => [
                        ['label' => 'NeoPHP version', 'value' => $data['frameworkVersion']],
                        ['label' => 'PHP version', 'value' => $data['phpVersion']],
                        ['label' => 'SAPI', 'value' => $data['sapi']],
                        ['label' => 'OS', 'value' => $data['os']],
                        ['label' => 'Environment', 'value' => $data['environment']],
                        ['label' => 'Timezone', 'value' => $data['timezone']],
                    ],
                ],
                [
                    'type' => 'kv',
                    'section' => 'PHP configuration',
                    'rows' => [
                        ['label' => 'Memory limit', 'value' => $data['memoryLimit']],
                        ['label' => 'Max execution time', 'value' => $data['maxExecutionTime'] . 's'],
                        ['label' => 'Upload max filesize', 'value' => $data['uploadMaxFilesize']],
                        ['label' => 'Post max size', 'value' => $data['postMaxSize']],
                        ['label' => 'OPcache', 'value' => $data['opcacheEnabled'] ? 'Enabled' : 'Disabled'],
                    ],
                ],
                [
                    'type' => 'table',
                    'section' => 'Composer packages (' . count($data['composerPackages']) . ')',
                    'columns' => ['Package', 'Version'],
                    'rows' => array_map(
                        static fn (array $pkg) => [$pkg['name'], $pkg['version']],
                        $data['composerPackages']
                    ),
                ],
            ],
        ];
    }

    private function resolveFrameworkVersion(): string
    {
        $composerVersion = $this->resolveFrameworkVersionFromComposer();

        if ($composerVersion !== null) {
            return $composerVersion;
        }

        $fileVersion = $this->resolveFrameworkVersionFromFile();

        if ($fileVersion !== null) {
            return $fileVersion;
        }

        return 'unknown';
    }

    private function resolveFrameworkVersionFromComposer(): ?string
    {
        try {
            $vendorPath = (string) $this->container->get('vendorPath');
        } catch (\Throwable) {
            return null;
        }

        $installedPath = rtrim($vendorPath, '/\\') . '/composer/installed.json';

        if (!is_file($installedPath)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($installedPath), true);
        $entries = $data['packages'] ?? $data ?? [];

        foreach ($entries as $entry) {
            if (($entry['name'] ?? null) === self::FRAMEWORK_PACKAGE_NAME) {
                return (string) ($entry['version'] ?? null) ?: null;
            }
        }

        return null;
    }

    private function resolveFrameworkVersionFromFile(): ?string
    {
        try {
            $basePath = (string) $this->container->get('basePath');
        } catch (\Throwable) {
            return null;
        }

        $versionFile = rtrim($basePath, '/\\') . '/VERSION';

        if (!is_file($versionFile)) {
            return null;
        }

        $version = trim((string) file_get_contents($versionFile));

        return $version !== '' ? $version : null;
    }

    private function resolveEnvironment(): string
    {
        try {
            return (string) ($this->container->get('profiler.configModule')->from('app')->get('environment') ?? 'unknown');
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    /**
     * @return list<array{name: string, version: string}>
     */
    private function composerPackages(): array
    {
        try {
            $vendorPath = (string) $this->container->get('vendorPath');
        } catch (\Throwable) {
            return [];
        }

        $installedPath = rtrim($vendorPath, '/\\') . '/composer/installed.json';

        if (!is_file($installedPath)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($installedPath), true);
        $entries = $data['packages'] ?? $data ?? [];

        $packages = [];

        foreach ($entries as $entry) {
            if (!isset($entry['name'])) {
                continue;
            }

            $packages[] = [
                'name' => (string) $entry['name'],
                'version' => (string) ($entry['version'] ?? 'n/a'),
            ];
        }

        usort($packages, static fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

        return $packages;
    }
}
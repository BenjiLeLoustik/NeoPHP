<?php

declare(strict_types=1);

namespace Neo\Core\Package\Collector;

use Neo\Core\DI\Container;
use Neo\Core\Package\Interface\PackageInterface;
use Neo\Core\Profiler\Interface\CollectorInterface;

final class PackagesCollector implements CollectorInterface
{
    /** @var array<string, array{version: string, description: string, source: string}>|null */
    private ?array $installedCache = null;

    public function __construct(private readonly Container $container)
    {
    }

    public function getName(): string
    {
        return 'packages';
    }

    public function collect(): array
    {
        if (!$this->container->has('packages')) {
            return [];
        }

        /** @var array<int, PackageInterface> $packages */
        $packages = $this->container->get('packages');
        $installed = $this->getInstalledPackages();

        $result = [];

        foreach ($packages as $package) {
            $composerName = $this->readComposerName($package->getPath());
            $meta = $composerName !== null ? ($installed[$composerName] ?? null) : null;

            $result[] = [
                'name' => $package->getName(),
                'composerName' => $composerName ?? '—',
                'version' => $meta['version'] ?? '—',
                'description' => $meta['description'] ?? '—',
                'source' => $meta['source'] ?? '',
            ];
        }

        return $result;
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
            'label' => 'Packages',
            'value' => '',
            'badge' => null,
        ];
    }

    public function profilerData(): array
    {
        $packages = $this->collect();

        return [
            'title' => 'Packages',
            'badge' => (string) count($packages),
            'badgeType' => 'neutral',
            'metrics' => [
                ['label' => 'Installed packages', 'value' => (string) count($packages)],
            ],
            'blocks' => [
                [
                    'type' => 'table',
                    'section' => null,
                    'columns' => ['Name', 'Version', 'Description', 'Source'],
                    'rows' => array_map(
                        static fn (array $p) => [
                            $p['name'],
                            $p['version'],
                            $p['description'],
                            $p['source'] !== '' ? $p['source'] : '—',
                        ],
                        $packages
                    ),
                ],
            ],
        ];
    }

    /**
     * @return array<string, array{version: string, description: string, source: string}>
     */
    private function getInstalledPackages(): array
    {
        if ($this->installedCache !== null) {
            return $this->installedCache;
        }

        $vendorPath = (string) $this->container->get('vendorPath');
        $path = rtrim($vendorPath, '/\\') . '/composer/installed.json';

        if (!is_file($path)) {
            return $this->installedCache = [];
        }

        $data = json_decode((string) file_get_contents($path), true);

        $entries = $data['packages'] ?? $data ?? [];

        $result = [];

        foreach ($entries as $entry) {
            if (!isset($entry['name'])) {
                continue;
            }

            $result[$entry['name']] = [
                'version' => (string) ($entry['version'] ?? '—'),
                'description' => (string) ($entry['description'] ?? '—'),
                'source' => $this->extractSourceUrl($entry),
            ];
        }

        return $this->installedCache = $result;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function extractSourceUrl(array $entry): string
    {
        if (isset($entry['support']['source'])) {
            return (string) $entry['support']['source'];
        }

        if (isset($entry['homepage'])) {
            return (string) $entry['homepage'];
        }

        if (isset($entry['source']['url'])) {
            return (string) $entry['source']['url'];
        }

        return '';
    }

    private function readComposerName(string $packagePath): ?string
    {
        $composerFile = rtrim($packagePath, '/\\') . '/composer.json';

        if (!is_file($composerFile)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($composerFile), true);

        return $data['name'] ?? null;
    }
}
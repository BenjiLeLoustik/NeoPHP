<?php
declare(strict_types=1);

namespace Neo\Core\Profiler;

use Neo\Core\DI\Container;
use Neo\Core\Event\EventModule;
use Neo\Core\Http\Response\ResponseModule;
use Neo\Core\Module\Interface\ModuleInterface;
use Neo\Core\Package\Interface\PackageInterface;
use Neo\Core\Package\PackageModule;
use Neo\Core\Profiler\Interface\CollectorInterface;
use Neo\Core\Profiler\Listener\ProfilerResponseListener;
use Neo\Core\Profiler\Toolbar\Toolbar;
use Neo\Core\Routing\RouterModule;
use Neo\Core\Security\Auth\AuthModule;
use Neo\Core\Translation\TranslationModule;
use Neo\Core\Utils\Config\ConfigModule;
use Neo\Core\Utils\Scanner\ScannerFileManager;
use ReflectionClass;

final class ProfilerModule implements ModuleInterface
{
    public function dependencies(): array
    {
        return [
            ResponseModule::class,
            EventModule::class,
            RouterModule::class,
            AuthModule::class,
            TranslationModule::class,
            ConfigModule::class,
            PackageModule::class,
        ];
    }

    public function register(Container $container): void {}

    public function init(Container $container): object
    {
        $profiler = ProfilerManager::getInstance();
        $container->set(ProfilerManager::class, fn() => $profiler);

        if (PHP_SAPI === 'cli') {
            return $profiler;
        }

        $env = $container->get('profiler.configModule')->from('app')->get('environment') ?? 'prod';

        if ($env !== 'dev') {
            return $profiler;
        }

        if (!defined('NEO_PROFILER_ENABLED')) {
            define('NEO_PROFILER_ENABLED', true);
        }

        self::ensureCollectorsRegistered($container, $profiler);

        $toolbar = new Toolbar($profiler);
        $container->set(Toolbar::class, fn() => $toolbar);

        $storageDir = $container->get('storagePath') . '/var/cache/profiler';
        $listener = new ProfilerResponseListener($toolbar, $profiler, $storageDir);

        $container->set(ProfilerResponseListener::class, fn() => $listener);

        $dispatcher = $container->get('profiler.eventModule');
        $dispatcher->addSubscriber($listener);

        return $profiler;
    }

    public static function ensureCollectorsRegistered(Container $container, ProfilerManager $profiler): void
    {
        if ($profiler->areCollectorsRegistered()) {
            return;
        }

        $coreDir = dirname(__DIR__);
        $targets = (new self())->buildScanTargets($container, $coreDir);
        $paths = array_map(static fn (array $t) => $t['path'], $targets);

        $results = new ScannerFileManager()
            ->paths($paths)
            ->withFilenameSuffix('Collector.php')
            ->withExcludedSegment('vendor', '.git', 'node_modules')
            ->scan();

        foreach ($results as $result) {
            $class = $result->getFqcn();

            if (str_contains($class, '\\Tests\\') || str_contains($class, '\\Fixture\\')) {
                continue;
            }

            if (!class_exists($class)) {
                continue;
            }

            $ref = new ReflectionClass($class);

            if ($ref->isAbstract() || $ref->isInterface() || !$ref->implementsInterface(CollectorInterface::class)) {
                continue;
            }

            try {
                /** @var CollectorInterface $collector */
                $collector = $container->get($class);
                $packageName = (new self())->resolvePackageForFile($ref->getFileName() ?: '', $targets);

                $profiler->addCollector($collector, $packageName);
            } catch (\Throwable $ex) {
                continue;
            }
        }

        $profiler->markCollectorsRegistered();
    }

    /**
     * @return list<array{path: string, package: string|null}>
     */
    private function buildScanTargets(Container $container, string $coreDir): array
    {
        $targets = [['path' => $coreDir, 'package' => null]];

        if ($container->has('appPath')) {
            $targets[] = ['path' => $container->get('appPath'), 'package' => null];
        }

        if ($container->has('packages')) {
            /** @var array<int, PackageInterface> $packages */
            $packages = $container->get('packages');

            foreach ($packages as $package) {
                $targets[] = ['path' => $package->getPath(), 'package' => $package->getName()];
            }
        }

        return $targets;
    }

    /**
     * @param list<array{path: string, package: string|null}> $targets
     */
    private function resolvePackageForFile(string $file, array $targets): ?string
    {
        $best = null;
        $bestLength = -1;

        foreach ($targets as $target) {
            $prefix = rtrim($target['path'], '/\\');

            if ($prefix !== '' && str_starts_with($file, $prefix) && strlen($prefix) > $bestLength) {
                $best = $target['package'];
                $bestLength = strlen($prefix);
            }
        }

        return $best;
    }
}
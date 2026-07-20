<?php
declare(strict_types=1);

namespace Neo\Core\Profiler;

use Neo\Core\DI\Container;
use Neo\Core\Event\Event\ResponseEvent;
use Neo\Core\Event\EventModule;
use Neo\Core\Http\Response\ResponseModule;
use Neo\Core\Module\Interface\ModuleInterface;
use Neo\Core\Profiler\Interface\CollectorAwareInterface;
use Neo\Core\Profiler\Interface\CollectorInterface;
use Neo\Core\Profiler\Listener\ProfilerResponseListener;
use Neo\Core\Profiler\Toolbar\Toolbar;
use Neo\Core\Routing\RouterModule;
use Neo\Core\Security\Auth\AuthModule;
use Neo\Core\Translation\TranslationModule;
use Neo\Core\Utils\Config\ConfigModule;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

class ProfilerModule implements ModuleInterface
{
    public function dependencies(): array
    {
        return [
            ResponseModule::class,
            EventModule::class,
            RouterModule::class,
            AuthModule::class,
            TranslationModule::class,
            ConfigModule::class
        ];
    }

    public function register(Container $container): void {}

    public function init(Container $container): object
    {
        $profiler = ProfilerManager::getInstance();

        if (php_sapi_name() === 'cli') {
            return $profiler;
        }

        $env = $container->get('profiler.configModule')->from('app')->get('environment') ?? 'prod';
        if ($env !== 'dev') {
            return $profiler;
        }

        if (!defined('NEO_PROFILER_ENABLED')) {
            define('NEO_PROFILER_ENABLED', true);
        }

        $dispatcher = $container->get('profiler.eventModule');

        $this->registerCollectors($container, $profiler);

        $toolbar = new Toolbar($profiler);
        $listener = new ProfilerResponseListener($toolbar);
        $dispatcher->addListenerInstance(ResponseEvent::class, $listener, 'onResponse');

        return $profiler;
    }

    private function registerCollectors(Container $container, ProfilerManager $profiler): void
    {
        $coreDir = dirname(__DIR__);

        $iterator = new RegexIterator(
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($coreDir)
            ),
            '/^.+Collector\.php$/i',
            RegexIterator::MATCH
        );

        foreach ($iterator as $file) {
            $class = $this->fileToClass($file->getRealPath(), $coreDir);

            if (str_contains($class, '\\Tests\\') || str_contains($class, '\\Fixture\\')) {
                continue;
            }

            if (!class_exists($class)) {
                continue;
            }

            $ref = new \ReflectionClass($class);

            if ($ref->isAbstract() || $ref->isInterface() || !$ref->implementsInterface(CollectorInterface::class)) {
                continue;
            }

            /** @var CollectorInterface $collector */
            $collector = $container->get($class);

            if ($collector instanceof CollectorAwareInterface) {
                $collector->boot($container);
            }

            $profiler->addCollector($collector);
        }
    }

    private function fileToClass(string $realPath, string $coreDir): string
    {
        $relative = str_replace([$coreDir . DIRECTORY_SEPARATOR, '.php'], ['', ''], $realPath);
        $relative = str_replace(DIRECTORY_SEPARATOR, '\\', $relative);

        return 'Neo\\Core\\' . $relative;
    }
}
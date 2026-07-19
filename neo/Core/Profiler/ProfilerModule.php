<?php
declare(strict_types=1);

namespace Neo\Core\Profiler;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Event\Event\ResponseEvent;
use Neo\Core\Event\EventManager;
use Neo\Core\Event\EventModule;
use Neo\Core\Http\HttpModule;
use Neo\Core\Module\Abstract\AbstractModule;
use Neo\Core\Profiler\Interface\CollectorAwareInterface;
use Neo\Core\Profiler\Interface\CollectorInterface;
use Neo\Core\Profiler\Listener\ProfilerResponseListener;
use Neo\Core\Profiler\Toolbar\Toolbar;
use Neo\Core\Routing\RouterModule;
use Neo\Core\Security\SecurityModule;
use Neo\Core\Translation\TranslationModule;
use Neo\Core\Utils\Config\ConfigManager;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

class ProfilerModule extends AbstractModule
{
    public function dependencies(): array
    {
        return [
            HttpModule::class,
            EventModule::class,
            RouterModule::class,
            SecurityModule::class,
            TranslationModule::class,
        ];
    }

    public function register(Container $container): void {}

    /**
     * @throws ContainerException
     */
    protected function resolveDependencies(): void
    {
        if (php_sapi_name() === 'cli') {
            return;
        }

        $env = $this->get(ConfigManager::class)->from('app')->get('environment') ?? 'prod';
        if ($env !== 'dev') {
            return;
        }

        if (!defined('NEO_PROFILER_ENABLED')) {
            define('NEO_PROFILER_ENABLED', true);
        }

        $profiler = ProfilerManager::getInstance();
        $dispatcher = $this->get(EventManager::class);

        $this->registerCollectors($profiler);

        $toolbar = new Toolbar($profiler);
        $listener = new ProfilerResponseListener($toolbar);
        $dispatcher->addListenerInstance(ResponseEvent::class, $listener, 'onResponse');
    }

    /**
     * @throws ContainerException
     */
    private function registerCollectors(ProfilerManager $profiler): void
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
            $collector = $this->get($class);

            if ($collector instanceof CollectorAwareInterface) {
                $collector->boot($this->get(Container::class));
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
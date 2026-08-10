<?php
declare(strict_types=1);

namespace Neo\Core\Error;

use Neo\Core\DI\Container;
use Neo\Core\Event\EventModule;
use Neo\Core\Module\Interface\ModuleInterface;
use Neo\Core\Utils\Config\ConfigModule;
use Neo\Core\Utils\Logger\LoggerModule;
use Neo\Core\View\ViewModule;

class ErrorModule implements ModuleInterface
{
    /**
     * @return list<class-string>
     */
    public function dependencies(): array
    {
        return [
            ConfigModule::class,
            EventModule::class,
            LoggerModule::class,
            ViewModule::class,
        ];
    }

    public function register(Container $container): void
    {
        $container->set(ErrorManager::class, fn(Container $c) => new ErrorManager($c));
    }

    public function init(Container $container): object
    {
        $errorHandler = $container->get(ErrorManager::class);

        if (empty($GLOBALS['_NEO_TEST_PROJECT'])) {
            $errorHandler->register();
        }

        try {
            $env = $container->get('error.configModule')
                ->from('app')
                ->get('environment') ?? 'prod';
            $errorHandler->setEnv($env);
        } catch (\Throwable) {
            // Config not resolvable yet at this point in boot
            // ErrorManager falls back to resolving env lazily on first use.
        }

        return $errorHandler;
    }
}
<?php
declare(strict_types=1);

namespace Neo\Core\Error;

use Neo\Core\DI\Container;
use Neo\Core\Module\AbstractModule;
use Neo\Core\Utils\Config\Config;
use Neo\Core\Utils\Config\ConfigModule;

class ErrorModule extends AbstractModule
{
    /**
     * @return list<class-string>
     */
    public function dependencies(): array
    {
        return [
            ConfigModule::class,
        ];
    }

    public function register(Container $container): void
    {
        $container->set(ErrorManager::class, fn(Container $c) => new ErrorManager($c));
    }

    protected function resolveDependencies(): void
    {
        $errorHandler = $this->get(ErrorManager::class);

        if (empty($GLOBALS['_NEO_TEST_PROJECT'])) {
            $errorHandler->register();
        }

        try {
            $env = $this->get(Config::class)
                ->from('app')
                ->get('environment') ?? 'prod';
            $errorHandler->setEnv($env);
        } catch (\Throwable) {}
    }
}
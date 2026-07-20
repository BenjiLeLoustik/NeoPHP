<?php
declare(strict_types=1);

namespace Neo\Core\Http\File;

use Neo\Core\DI\Container;
use Neo\Core\Module\Abstract\AbstractModule;
use Neo\Core\Utils\Config\ConfigModule;

class UploaderModule extends AbstractModule
{
    public function dependencies(): array
    {
        return [
            ConfigModule::class,
        ];
    }

    public function register(Container $container): void
    {
        $container->set(UploaderManager::class, fn(Container $c) => new UploaderManager($c));
    }

    protected function resolveDependencies(): void
    {
        $this->get(UploaderManager::class);
    }
}
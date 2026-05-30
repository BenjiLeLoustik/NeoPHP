<?php
declare(strict_types=1);

namespace Neo\Core\Database;

use Neo\Core\Database\Form\FormExtension;
use Neo\Core\DI\Container;
use Neo\Core\Module\AbstractModule;
use Neo\Core\Utils\Config\ConfigModule;
use Neo\Core\View\ViewModule;

class DatabaseModule extends AbstractModule
{
    public function dependencies(): array
    {
        return [
            ConfigModule::class,
            ViewModule::class,
        ];
    }

    public function register(Container $container): void
    {
        $container->set(DatabaseConnection::class, fn(Container $c) => new DatabaseConnection($c));
        $container->set(DatabaseManager::class, fn() => new DatabaseManager());
    }

    protected function resolveDependencies(): void
    {
        $this->get(DatabaseConnection::class);

        FormExtension::register($this->container);
    }
}
<?php
declare(strict_types=1);

namespace Neo\Core\Database;

use Neo\Core\Database\Access\Connection\DatabaseConnection;
use Neo\Core\Database\ORM\EntityManager;
use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Module\Interface\ModuleInterface;
use Neo\Core\Translation\TranslationModule;
use Neo\Core\Utils\Config\ConfigModule;
use Neo\Core\View\ViewModule;

class DatabaseModule implements ModuleInterface
{
    /**
     * @return array<int, class-string>
     */
    public function dependencies(): array
    {
        return [
            ConfigModule::class,
            ViewModule::class,
            TranslationModule::class
        ];
    }

    public function register(Container $container): void
    {
        $container->set(DatabaseConnection::class, fn(Container $c) => new DatabaseConnection($c));
        $container->set(DatabaseManager::class, fn() => new DatabaseManager());
        $container->set(EntityManager::class, fn(Container $c) => new EntityManager($c));
    }

    /**
     * @throws ContainerException
     */
    public function init(Container $container): object
    {
        return $container->get(DatabaseConnection::class);
    }
}
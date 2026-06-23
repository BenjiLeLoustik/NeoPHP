<?php
declare(strict_types=1);

namespace Neo\Core\Database;

use Neo\Core\Database\Form\FormViewExtension;
use Neo\Core\Database\ORM\EntityManager;
use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Module\Abstract\AbstractModule;
use Neo\Core\Translation\TranslationManager;
use Neo\Core\Translation\TranslationModule;
use Neo\Core\Utils\Config\ConfigModule;
use Neo\Core\View\ViewModule;

class DatabaseModule extends AbstractModule
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
        $container->set(DatabaseViewExtension::class, fn() => new DatabaseViewExtension());
        $container->set(FormViewExtension::class, fn(Container $c) => new FormViewExtension($c->get(TranslationManager::class)));
        $container->tag(DatabaseViewExtension::class, 'twig.extension');
        $container->tag(FormViewExtension::class, 'twig.extension');
    }

    /**
     * @throws ContainerException
     */
    protected function resolveDependencies(): void
    {
        $this->get(DatabaseConnection::class);
    }
}
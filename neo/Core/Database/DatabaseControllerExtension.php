<?php
declare(strict_types=1);

namespace Neo\Core\Database;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\Database\ORM\EntityManager;
use Neo\Core\DI\Container;

final class DatabaseControllerExtension implements ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void
    {
        $controller->registerMethod('getDatabase', fn(): DatabaseFacade => new DatabaseFacade());
        $controller->registerProperty('entityManager', fn(): EntityManager => $container->get(EntityManager::class));
    }
}
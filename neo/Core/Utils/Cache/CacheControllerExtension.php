<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Cache;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;

/**
 * @method \Neo\Core\Utils\Cache\Cache getCache()
 */
class CacheControllerExtension implements ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void
    {
        $controller->registerMethod('getCache', fn() => $container->get(Cache::class));
    }
}
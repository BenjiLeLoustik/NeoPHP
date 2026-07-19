<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Cache;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;
use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;

/**
 * @method \Neo\Core\Utils\Cache\Cache getCache()
 */
#[Extension(type: ExtensionTypeEnum::CONTROLLER)]
class CacheControllerExtension implements ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void
    {
        $controller->registerMethod('getCache', fn() => $container->get(Cache::class));
    }
}
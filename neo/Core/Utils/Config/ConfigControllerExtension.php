<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Config;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;
use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;

/**
 * @method \Neo\Core\Utils\Config\Config getConfig()
 */
#[Extension(type: ExtensionTypeEnum::CONTROLLER)]
class ConfigControllerExtension implements ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void
    {
        $controller->registerMethod('getConfig', fn() => $container->get(Config::class));
    }
}
<?php

namespace Neo\Core\Asset\Extension;

use Neo\Core\Asset\AssetManager;
use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;
use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;

/**
 * @method string getAsset(string $path)
 */
#[Extension(type: ExtensionTypeEnum::CONTROLLER)]
class AssetControllerExtension implements ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void
    {
        $controller->registerMethod('getAsset', fn (string $path) =>
            $container->get(AssetManager::class)->getAssetPath($path)
        );
    }
}
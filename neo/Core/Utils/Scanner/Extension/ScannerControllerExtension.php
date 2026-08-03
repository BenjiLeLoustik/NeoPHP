<?php

namespace Neo\Core\Utils\Scanner\Extension;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;
use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\Utils\Scanner\ScannerAttributeManager;
use Neo\Core\Utils\Scanner\ScannerFileManager;

/**
 * @method \Neo\Core\Utils\Scanner\ScannerAttributeManager getScanner(string $classname)
 * @method \Neo\Core\Utils\Scanner\ScannerFileManager getFileScanner();
 */
#[Extension(type: ExtensionTypeEnum::CONTROLLER)]
class ScannerControllerExtension implements ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void
    {
        $controller->registerMethod('getScanner', fn(string $className) => new ScannerAttributeManager($className));
        $controller->registerMethod('getFileScanner', fn() => new ScannerFileManager());
    }
}
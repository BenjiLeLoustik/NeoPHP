<?php

namespace Neo\Core\Utils\Scanner;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;
use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\Utils\Scanner\Attribute\ScannerAttribute;

/**
 * @method Attribute\ScannerAttribute getScanner(string $classname)
 */
#[Extension(type: ExtensionTypeEnum::CONTROLLER)]
class ScannerControllerExtension implements ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void
    {
        $controller->registerMethod('getScanner', fn(string $className) => new ScannerAttribute($className));
    }
}
<?php

namespace Neo\Core\Utils\Scanner;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;

/**
 * @method \Neo\Core\Utils\Scanner\AttributeScanner getScanner(string $classname)
 */
enum ScannerControllerExtension implements ControllerExtensionInterface
{

    public function extend(AbstractController $controller, Container $container): void
    {
        $controller->registerMethod('getScanner', fn(string $className) => new AttributeScanner($className));
    }
}

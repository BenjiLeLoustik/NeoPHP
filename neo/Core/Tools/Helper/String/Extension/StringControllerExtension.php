<?php

namespace Neo\Core\Tools\Helper\String\Extension;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;
use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\Tools\Helper\String\StringHelper;

#[Extension(type: ExtensionTypeEnum::CONTROLLER)]
class StringControllerExtension implements ControllerExtensionInterface
{

    public function extend(AbstractController $controller, Container $container): void
    {
        $controller->registerMethod('slugify', fn(string $value) => StringHelper::slugify($value));
    }
}
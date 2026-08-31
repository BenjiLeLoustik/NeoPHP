<?php

namespace Neo\Core\Tools\Helper\Number\Extension;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;
use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\Tools\Helper\Number\NumberHelper;

#[Extension(type: ExtensionTypeEnum::CONTROLLER)]
class NumberControllerExtension implements ControllerExtensionInterface
{

    public function extend(AbstractController $controller, Container $container): void
    {
        $controller->registerMethod(
            'compactNumber',
            fn(int|float $number, int $precision = 1) => NumberHelper::compact($number, $precision)
        );

        $controller->registerMethod(
            'numberFormatPrice',
            fn(float $number, int $precision = 1) => NumberHelper::formatDecimal($number, $precision)
        );
    }
}
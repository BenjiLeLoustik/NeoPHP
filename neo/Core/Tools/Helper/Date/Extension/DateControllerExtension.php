<?php
declare(strict_types=1);

namespace Neo\Core\Tools\Helper\Date\Extension;

use DateTimeInterface;
use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;
use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\Tools\Helper\Date\DateHelper;

#[Extension(type: ExtensionTypeEnum::CONTROLLER)]
class DateControllerExtension implements ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void
    {
        $controller->registerMethod(
            'timeAgo',
            fn(DateTimeInterface|string $datetime) => DateHelper::timeAgo($datetime)
        );
    }
}
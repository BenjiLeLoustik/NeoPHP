<?php
declare(strict_types=1);

namespace Neo\Core\Http\Client\Cookie\Extension;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;
use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\Http\Client\Cookie\Cookie;

#[Extension(type: ExtensionTypeEnum::CONTROLLER)]
final class CookieControllerExtension implements ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void
    {
        $controller->registerMethod('getCookie', fn(): Cookie => $container->get(Cookie::class));
    }
}
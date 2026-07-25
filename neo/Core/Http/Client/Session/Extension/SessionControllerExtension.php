<?php
declare(strict_types=1);

namespace Neo\Core\Http\Client\Session\Extension;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;
use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\Http\Client\Session\Session;

#[Extension(type: ExtensionTypeEnum::CONTROLLER)]
final class SessionControllerExtension implements ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void
    {
        $controller->registerMethod('getSession', fn(): Session => $container->get(Session::class));
    }
}
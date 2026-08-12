<?php
declare(strict_types=1);

namespace Neo\Core\Http\Client\Session\Extension;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;
use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\Http\Client\Session\Session;

/**
 * @method \Neo\Core\Http\Client\Session\Session getSession()
 */
#[Extension(type: ExtensionTypeEnum::CONTROLLER)]
class SessionControllerExtension implements ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void
    {
        $controller->registerMethod('getSession', function () use ($container): Session {
            return $container->get(Session::class);
        });
    }
}
<?php
declare(strict_types=1);

namespace Neo\Core\Http\Client\Flash\Extension;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;
use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\Http\Client\Flash\Flash;

/**
 * @method \Neo\Core\Http\Client\Flash\Flash getFlash()
 */
#[Extension(type: ExtensionTypeEnum::CONTROLLER)]
class FlashControllerExtension implements ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void
    {
        $flash = $container->get(Flash::class);

        $controller->registerMethod('getFlash', fn(): Flash => $flash);
    }
}
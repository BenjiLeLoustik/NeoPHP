<?php
declare(strict_types=1);

namespace Neo\Core\Event;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;
use Neo\Core\Event\Interface\EventInterface;
use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;

/**
 * @method \Neo\Core\Event\Interface\EventInterface dispatch(\Neo\Core\Event\Interface\EventInterface $event)
 */
#[Extension(type: ExtensionTypeEnum::CONTROLLER)]
class EventControllerExtension implements ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void
    {
        $controller->registerMethod('dispatch', function (EventInterface $event) use ($container) {
            return $container->get(EventManager::class)->dispatch($event);
        });
    }
}
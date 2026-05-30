<?php
declare(strict_types=1);

namespace Neo\Core\Event;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;
use Neo\Core\Event\Contract\EventInterface;

/**
 * @method \Neo\Core\Event\Contract\EventInterface dispatch(\Neo\Core\Event\Contract\EventInterface $event)
 */
class EventControllerExtension implements ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void
    {
        $controller->registerMethod('dispatch', function (EventInterface $event) use ($container) {
            return $container->get(EventDispatcher::class)->dispatch($event);
        });
    }
}
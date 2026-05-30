<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Mailer;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;

/**
 * @method \Neo\Core\Utils\Mailer\Mailer getMailer()
 */
class MailerControllerExtension implements ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void
    {
        $controller->registerMethod('getMailer', fn() => $container->get(Mailer::class));
    }
}
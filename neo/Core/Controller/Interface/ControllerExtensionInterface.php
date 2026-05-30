<?php
declare(strict_types=1);

namespace Neo\Core\Controller\Interface;

use Neo\Core\Controller\AbstractController;
use Neo\Core\DI\Container;

interface ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void;
}
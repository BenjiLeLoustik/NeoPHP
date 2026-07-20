<?php
declare(strict_types=1);

namespace Neo\Core\Validator;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Module\Interface\ModuleInterface;

class ValidatorModule implements ModuleInterface
{
    public function dependencies(): array
    {
        return [];
    }

    public function register(Container $container): void
    {
        $container->set(ValidatorManager::class, fn() => new ValidatorManager());
    }

    /**
     * @throws ContainerException
     */
    public function init(Container $container): object
    {
        return $container->get(ValidatorManager::class);
    }
}
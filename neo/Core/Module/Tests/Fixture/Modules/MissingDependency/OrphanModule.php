<?php

namespace Neo\Core\Module\Tests\Fixture\Modules\MissingDependency;

use Neo\Core\DI\Container;
use Neo\Core\Module\Interface\ModuleInterface;

class OrphanModule implements ModuleInterface
{
    public function dependencies(): array
    {
        return ['Neo\\Core\\Module\\Tests\\Fixture\\Modules\\MissingDependency\\DoesNotExistModule'];
    }

    public function register(Container $container): void
    {}

    public function boot(Container $container): void
    {}
}
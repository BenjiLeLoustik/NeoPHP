<?php

namespace Neo\Core\Module\Tests\Fixture\Modules\Circular;

use Neo\Core\DI\Container;
use Neo\Core\Module\Interface\ModuleInterface;

class BModule implements ModuleInterface
{
    public function dependencies(): array
    {
        return [AModule::class];
    }

    public function register(Container $container): void
    {}

    public function boot(Container $container): void
    {}
}
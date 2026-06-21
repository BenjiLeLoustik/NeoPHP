<?php

namespace Neo\Core\Module\Tests\Fixture\Modules\Invalid;

use Neo\Core\DI\Container;
use Neo\Core\Module\Interface\ModuleInterface;

abstract class AbstractSkippedModule implements ModuleInterface
{
    public function dependencies(): array
    {
        return [];
    }

    public function register(Container $container): void
    {}

    public function boot(Container $container): void
    {}
}
<?php

namespace Neo\Core\Module\Tests\Fixture\Modules\Dependency;

use Neo\Core\DI\Container;
use Neo\Core\Module\Interface\ModuleInterface;
use Neo\Core\Module\Tests\Fixture\ModuleCallLog;

class BModule implements ModuleInterface
{
    public function dependencies(): array
    {
        return [];
    }

    public function register(Container $container): void
    {
        ModuleCallLog::$calls[] = self::class . '::register';
    }

    public function boot(Container $container): void
    {
        ModuleCallLog::$calls[] = self::class . '::boot';
    }
}
<?php
declare(strict_types=1);

namespace Neo\Core\Http\File;

use Neo\Core\DI\Container;
use Neo\Core\Module\Interface\ModuleInterface;

class UploaderModule implements ModuleInterface
{
    public function dependencies(): array
    {
        return [];
    }

    public function register(Container $container): void
    {
        $container->set(UploaderManager::class, fn(Container $c) => new UploaderManager($c));
    }

    public function init(Container $container): object
    {
        return $container->get(UploaderManager::class);
    }
}
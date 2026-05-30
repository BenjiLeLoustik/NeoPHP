<?php

namespace Neo\Core\Module\Interface;

use Neo\Core\DI\Container;

interface ModuleInterface
{
    public function dependencies(): array;
    public function register(Container $container): void;
    public function boot(Container $container): void;
}
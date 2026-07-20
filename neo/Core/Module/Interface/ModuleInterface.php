<?php

namespace Neo\Core\Module\Interface;

use Neo\Core\DI\Container;

interface ModuleInterface
{
    /**
     * @return array<class-string>
     */
    public function dependencies(): array;

    public function register(Container $container): void;

    public function init(Container $container): object;
}
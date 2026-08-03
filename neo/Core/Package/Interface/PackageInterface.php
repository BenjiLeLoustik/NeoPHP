<?php

namespace Neo\Core\Package\Interface;

use Neo\Core\DI\Container;

interface PackageInterface
{
    public function getName(): string;
    public function getPath(): string;
    public function register(Container $container): void;
}
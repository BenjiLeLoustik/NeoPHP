<?php

namespace Neo\Core\Package\Abstract;

use Neo\Core\DI\Container;
use Neo\Core\Package\Interface\PackageInterface;

abstract class AbstractPackage implements PackageInterface
{
    public function register(Container $container): void
    {}
}
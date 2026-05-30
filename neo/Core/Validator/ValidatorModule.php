<?php
declare(strict_types=1);

namespace Neo\Core\Validator;

use Neo\Core\DI\Container;
use Neo\Core\Module\AbstractModule;

class ValidatorModule extends AbstractModule
{
    public function dependencies(): array
    {
        return [];
    }

    public function register(Container $container): void
    {
        $container->set(Validator::class, fn() => new Validator());
    }
}
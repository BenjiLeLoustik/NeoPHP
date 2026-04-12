<?php
declare(strict_types=1);

namespace Neo\Core\Security\Middleware\Default;

use Neo\Core\Security\Middleware\Interface\MiddlewareInterface;

class ExampleMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        return false;
    }
}

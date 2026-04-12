<?php
declare(strict_types=1);

namespace Neo\Core\Security\Middleware\Interface;

interface MiddlewareInterface
{
    public function handle(): bool;
}

<?php
declare(strict_types=1);

namespace Neo\Core\Security\Middleware\Default;

use Neo\Core\DI\Container;
use Neo\Core\Security\Auth\AuthManager;
use Neo\Core\Security\Middleware\Interface\MiddlewareInterface;

class GuestMiddleware implements MiddlewareInterface
{
    private AuthManager $auth;

    public function __construct(Container $container)
    {
        $this->auth = $container->get(AuthManager::class);
    }

    public function handle(): bool
    {
        return !$this->auth->check();
    }
}
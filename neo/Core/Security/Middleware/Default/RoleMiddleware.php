<?php
declare(strict_types=1);

namespace Neo\Core\Security\Middleware\Default;

use Neo\Core\DI\Container;
use Neo\Core\Security\Auth\AuthManager;
use Neo\Core\Security\Middleware\Interface\MiddlewareInterface;

class RoleMiddleware implements MiddlewareInterface
{
    private AuthManager $auth;
    private string      $role;

    public function __construct(Container $container, string $role = 'admin')
    {
        $this->auth = $container->get(AuthManager::class);
        $this->role = $role;
    }

    public function handle(): bool
    {
        return $this->auth->hasRole($this->role);
    }
}
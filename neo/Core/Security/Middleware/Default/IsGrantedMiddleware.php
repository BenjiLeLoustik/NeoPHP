<?php
declare(strict_types=1);

namespace Neo\Core\Security\Middleware\Default;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Security\Auth\AuthManager;
use Neo\Core\Security\Middleware\Interface\MiddlewareInterface;

class IsGrantedMiddleware implements MiddlewareInterface
{
    private AuthManager $auth;

    /** @var array<int, string> */
    private array $roles;

    /**
     * @param array<int, string> $roles
     * @throws ContainerException
     * @throws \ReflectionException
     */
    public function __construct(Container $container, array $roles = [])
    {
        $this->auth = $container->get(AuthManager::class);
        $this->roles = $roles;
    }

    public function handle(): bool
    {
        return $this->roles === [] || array_any($this->roles, fn ($role) => $this->auth->hasRole($role));
    }
}
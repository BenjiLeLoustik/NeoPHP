<?php
declare(strict_types=1);

namespace Neo\Core\Security\Auth;

use Neo\Core\Database\ORM\Model\AbstractModel;
use Neo\Core\DI\Container;
use Neo\Core\Error\Exception\FrameworkException;
use Neo\Core\Http\Client\Session;
use Neo\Core\Security\Auth\Guard\SessionGuard;
use Neo\Core\Security\PasswordManager;
use Neo\Core\Utils\Config;
use Neo\Core\View\View;

class AuthManager
{
    private Container $container;
    private ?SessionGuard $guard = null;
    private array $config;

    public function __construct(Container $container)
    {
        $this->container = $container;

        $this->config = $container->get(Config::class)
            ->from('app')
            ->get('auth') ?? [];

        if (isset($this->config['enabled']) && $this->config['enabled'] === false) {
            return;
        }

        if (empty($this->config['model'])) {
            throw new FrameworkException(
                title: 'Auth Configuration Error',
                message: "La configuration 'auth.model' est manquante dans app.config.php.",
                code: 500
            );
        }

        $this->guard = new SessionGuard(
            $container->get(Session::class),
            $container->get(PasswordManager::class),
            $this->config['model'],
            $this->config['identifier'] ?? 'email',
            $this->config['password']   ?? 'password',
            $this->config['role'] ?? ''
        );
    }

    public function attempt(array $credentials): bool
    {
        $this->ensureEnabled();
        return $this->guard->attempt($credentials);
    }

    public function login(AbstractModel $user): void
    {
        $this->ensureEnabled();
        $this->guard->login($user);
    }

    public function logout(): void
    {
        $this->ensureEnabled();
        $this->guard->logout();
    }

    public function check(): bool
    {
        if ($this->guard === null) return false;
        return $this->guard->check();
    }

    public function user(): ?AbstractModel
    {
        if ($this->guard === null) return null;
        return $this->guard->user();
    }

    public function hasRole(string $role): bool
    {
        if ($this->guard === null) return false;
        return $this->guard->hasRole($role);
    }

    private function ensureEnabled(): void
    {
        if ($this->guard === null) {
            throw new FrameworkException(
                title: 'Auth Disabled',
                message: "Le système d'authentification est désactivé dans app.config.php.",
                code: 500
            );
        }
    }
}
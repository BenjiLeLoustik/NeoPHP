<?php
declare(strict_types=1);

namespace Neo\Core\Security\Auth;

use Neo\Core\Database\ORM\Model\AbstractModel;
use Neo\Core\DI\Container;
use Neo\Core\Http\Client\Session;
use Neo\Core\Http\Request;
use Neo\Core\Security\Auth\Exception\AuthException;
use Neo\Core\Security\Auth\Guard\GuardInterface;
use Neo\Core\Security\Auth\Guard\SessionGuard;
use Neo\Core\Security\Auth\Guard\TokenGuard;
use Neo\Core\Security\Password\PasswordManager;
use Neo\Core\Utils\Config\Config;

class AuthManager
{
    private Container $container;
    private ?GuardInterface $guard = null;
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
            throw new AuthException(
                title: 'Auth Configuration Error',
                message: "The 'auth.model' configuration is missing in app.config.php.",
                code: 500
            );
        }

        $this->guard = $this->resolveGuard();
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

    public function generateToken(AbstractModel $user): string
    {
        $this->ensureEnabled();

        if (!$this->guard instanceof TokenGuard) {
            throw new AuthException(
                title: 'Auth Error',
                message: "generateToken() is only available with the 'token' guard.",
                code: 500
            );
        }

        return $this->guard->generateToken($user);
    }

    private function resolveGuard(): GuardInterface
    {
        $guardType = $this->config['guard'] ?? 'session';
        $options = $this->config['options'] ?? [];
        $role = $this->config['role'] ?? [];

        return match($guardType) {
            'token' => new TokenGuard(
                $this->container->get(Request::class),
                new JwtManager(
                    $options['secret'] ?? '',
                    $options['expiration'] ?? 3600,
                    $options['algorithm']  ?? 'HS256'
                ),
                $this->container->get(PasswordManager::class),
                $this->config['model'],
                $this->config['identifier'] ?? 'email',
                $this->config['password'] ?? 'password',
                $role
            ),
            default => new SessionGuard(
                $this->container->get(Session::class),
                $this->container->get(PasswordManager::class),
                $this->config['model'],
                $this->config['identifier'] ?? 'email',
                $this->config['password'] ?? 'password',
                $role
            ),
        };
    }

    private function ensureEnabled(): void
    {
        if ($this->guard === null) {
            throw new AuthException(
                title: 'Auth Disabled',
                message: "The authentication system is disabled in app.config.php.",
                code: 500
            );
        }
    }
}
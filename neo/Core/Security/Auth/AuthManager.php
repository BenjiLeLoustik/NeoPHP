<?php
declare(strict_types=1);

namespace Neo\Core\Security\Auth;

use Neo\Core\Database\ORM\Model\AbstractModel;
use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Http\Client\Session\Session;
use Neo\Core\Http\Request;
use Neo\Core\Security\Auth\Exception\AuthException;
use Neo\Core\Security\Auth\Exception\JwtException;
use Neo\Core\Security\Auth\Guard\Interface\GuardInterface;
use Neo\Core\Security\Auth\Guard\SessionGuard;
use Neo\Core\Security\Auth\Guard\TokenGuard;
use Neo\Core\Utils\Config\Config;

class AuthManager
{
    private Container $container;

    private ?GuardInterface $guard = null;

    /** @var array<string, mixed> */
    private array $config;

    /**
     * @throws ContainerException
     * @throws AuthException
     * @throws JwtException
     */
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

    /**
     * @param array<string, mixed> $credentials
     * @throws AuthException
     */
    public function attempt(array $credentials): bool
    {
        $this->ensureEnabled();
        return $this->guard->attempt($credentials);
    }

    /**
     * @throws AuthException
     */
    public function login(AbstractModel $user): void
    {
        $this->ensureEnabled();
        $this->guard->login($user);
    }

    /**
     * @throws AuthException
     */
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

    /**
     * @throws AuthException
     */
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

    /**
     * @throws ContainerException
     * @throws JwtException
     */
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
                $role,
                (int) ($options['timeout'] ?? 1800)
            ),
        };
    }

    /**
     * @throws AuthException
     */
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
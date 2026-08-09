<?php
declare(strict_types=1);

namespace Neo\Core\Security\Auth;

use Neo\Core\Database\ORM\Persistence\EntityManager;
use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Http\Request\Request;
use Neo\Core\Security\Auth\Config\RoleConfig;
use Neo\Core\Security\Auth\Exception\AuthException;
use Neo\Core\Security\Auth\Exception\JwtException;
use Neo\Core\Security\Auth\Guard\Interface\GuardInterface;
use Neo\Core\Security\Auth\Guard\SessionGuard;
use Neo\Core\Security\Auth\Guard\TokenGuard;
use Neo\Core\Utils\Config\Exception\ConfigException;

class AuthManager
{
    private Container $container;

    private ?GuardInterface $guard = null;

    private ?RoleConfig $roleConfig = null;

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

        try {
            $this->config = $container->get('auth.configModule')->from('auth')->all();
        } catch (ConfigException) {
            $this->config = [];
        }

        if (isset($this->config['enabled']) && $this->config['enabled'] === false) {
            return;
        }

        if (empty($this->config['model'])) {
            throw new AuthException(
                title: 'Auth Configuration Error',
                message: "The 'model' configuration is missing in auth.config.php.",
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
    public function login(object $user): void
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

    public function user(): ?object
    {
        if ($this->guard === null) return null;
        return $this->guard->user();
    }

    public function hasRole(string $role): bool
    {
        if ($this->guard === null) return false;
        return $this->guard->hasRole($role);
    }

    public function getRoleConfig(): ?RoleConfig
    {
        return $this->roleConfig;
    }

    /**
     * @throws AuthException
     */
    public function generateToken(object $user): string
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
        $role = is_array($this->config['role'] ?? null) ? $this->config['role'] : [];

        $roleConfig = RoleConfig::fromArray($role);
        $this->roleConfig = $roleConfig;

        $em = $this->container->get(EntityManager::class);

        return match($guardType) {
            'token' => new TokenGuard(
                $this->container->get(Request::class),
                new JwtManager(
                    $options['secret'] ?? '',
                    $options['expiration'] ?? 3600,
                    $options['algorithm']  ?? 'HS256'
                ),
                $this->container->get(PasswordManager::class),
                $em,
                $this->config['model'],
                $this->config['identifier'] ?? 'email',
                $this->config['password'] ?? 'password',
                $roleConfig
            ),
            default => new SessionGuard(
                $this->container->get('auth.clientModule')->session(),
                $this->container->get(PasswordManager::class),
                $em,
                $this->config['model'],
                $this->config['identifier'] ?? 'email',
                $this->config['password'] ?? 'password',
                $roleConfig,
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
                message: "The authentication system is disabled in auth.config.php.",
                code: 500
            );
        }
    }

    public function isEnabled(): bool
    {
        return $this->guard !== null;
    }

    public function getGuardType(): string
    {
        return $this->config['guard'] ?? 'session';
    }

    public function getIdentifierField(): string
    {
        return $this->config['identifier'] ?? 'email';
    }
}
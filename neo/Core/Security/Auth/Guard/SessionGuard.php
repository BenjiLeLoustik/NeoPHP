<?php
declare(strict_types=1);

namespace Neo\Core\Security\Auth\Guard;

use Neo\Core\Database\Exception\DatabaseException;
use Neo\Core\Database\Form\PropertyAccessor;
use Neo\Core\Database\ORM\Persistence\EntityManager;
use Neo\Core\Http\Client\Cookie\Cookie;
use Neo\Core\Http\Client\Session\Session;
use Neo\Core\Security\Auth\Config\RoleConfig;
use Neo\Core\Security\Auth\Exception\AuthException;
use Neo\Core\Security\Auth\Guard\Interface\GuardInterface;
use Neo\Core\Security\Auth\PasswordManager;
use Neo\Core\Utils\Cache\CacheManager;
use Neo\Core\Utils\Cache\Exception\CacheException;

final class SessionGuard implements GuardInterface
{
    private const string SESSION_KEY = '_auth_user_id';
    private const string SESSION_LAST_ACTIVITY_KEY = '_auth_last_activity';
    private const int DEFAULT_TIMEOUT = 1800;
    private const string CACHE_PREFIX = 'remember_token:';

    private PropertyAccessor $accessor;

    /**
     * @param class-string $model
     * @param array{enabled: bool, cookie: string, expiration: int} $remember
     */
    public function __construct(
        private Session $session,
        private PasswordManager $passwordManager,
        private EntityManager $em,
        private CacheManager $cache,
        private Cookie $cookie,
        private string $model,
        private string $identifier,
        private string $password,
        private ?RoleConfig $role = null,
        private int $timeout = self::DEFAULT_TIMEOUT,
        private array $remember = ['enabled' => false, 'cookie' => 'remember_token', 'expiration' => 2592000],
    ) {
        $this->accessor = new PropertyAccessor();
    }

    /**
     * @param array<string, mixed> $credentials
     * @throws AuthException
     * @throws DatabaseException
     */
    public function attempt(array $credentials, bool $remember = false): bool
    {
        if (!isset($credentials[$this->identifier], $credentials[$this->password])) {
            throw new AuthException(
                title: 'Auth Error',
                message: sprintf(
                    "Credentials must contain '%s' and '%s'.",
                    $this->identifier,
                    $this->password
                ),
                code: 400
            );
        }

        $user = $this->findByIdentifier($credentials[$this->identifier]);

        if ($user === null) {
            return false;
        }

        $hashedPassword = $this->accessor->getValue($user, $this->password);

        if (!is_string($hashedPassword) || $hashedPassword === '') {
            return false;
        }

        if (!$this->passwordManager->verify($credentials[$this->password], $hashedPassword)) {
            return false;
        }

        $this->login($user, $remember);

        return true;
    }

    public function login(object $user, bool $remember = false): void
    {
        $id = $this->em->getClassMetadata($user::class)->getIdentifierValue($user);

        $this->session->regenerate();
        $this->session->set(self::SESSION_KEY, $id);
        $this->session->set(self::SESSION_LAST_ACTIVITY_KEY, time());

        if ($remember && $this->rememberEnabled()) {
            $this->createRememberCookie($user::class, $id);
        }
    }

    public function logout(): void
    {
        $this->session->remove(self::SESSION_KEY);
        $this->session->remove(self::SESSION_LAST_ACTIVITY_KEY);

        if ($this->rememberEnabled()) {
            $this->forgetRememberCookie();
        }
    }

    public function check(): bool
    {
        if ($this->session->has(self::SESSION_KEY)) {
            $lastActivity = $this->session->get(self::SESSION_LAST_ACTIVITY_KEY, 0);

            if ((time() - $lastActivity) <= $this->timeout) {
                $this->session->set(self::SESSION_LAST_ACTIVITY_KEY, time());
                return true;
            }

            $this->session->remove(self::SESSION_KEY);
            $this->session->remove(self::SESSION_LAST_ACTIVITY_KEY);
        }

        if (!$this->rememberEnabled()) {
            return false;
        }

        return $this->resumeFromRememberCookie();
    }

    /**
     * @throws DatabaseException
     */
    public function user(): ?object
    {
        if (!$this->check()) {
            return null;
        }

        $id = $this->session->get(self::SESSION_KEY);
        $user = $this->em->find($this->model, $id);

        if ($user === null) {
            $this->logout();
            return null;
        }

        return $user;
    }

    /**
     * @throws DatabaseException
     */
    public function hasRole(string $role): bool
    {
        if ($this->role === null) {
            return false;
        }

        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $roleValue = $this->accessor->getValue($user, $this->role->getRelation());

        if ($roleValue === null) {
            return false;
        }

        $field = $this->role->getField();

        if (is_object($roleValue)) {
            return $this->accessor->getValue($roleValue, $field) === $role;
        }

        $roleEntity = $this->em->find($this->role->getModel(), $roleValue);

        return $roleEntity !== null && $this->accessor->getValue($roleEntity, $field) === $role;
    }

    private function rememberEnabled(): bool
    {
        return (bool) ($this->remember['enabled'] ?? false);
    }

    private function rememberCookieName(): string
    {
        $name = $this->remember['cookie'] ?? 'remember_token';
        return is_string($name) && $name !== '' ? $name : 'remember_token';
    }

    private function rememberTtl(): int
    {
        $ttl = $this->remember['expiration'] ?? 2592000;
        return is_int($ttl) && $ttl > 0 ? $ttl : 2592000;
    }

    private function createRememberCookie(string $model, mixed $id): void
    {
        $token = bin2hex(random_bytes(32));
        $ttl = $this->rememberTtl();

        try {
            $this->cache->set(self::CACHE_PREFIX . $token, ['model' => $model, 'id' => $id], $ttl);
        } catch (CacheException) {
            return;
        }

        $this->cookie->set($this->rememberCookieName(), $token, time() + $ttl);
    }

    private function forgetRememberCookie(): void
    {
        $token = $this->cookie->get($this->rememberCookieName());

        if (is_string($token) && $token !== '') {
            try {
                $this->cache->delete(self::CACHE_PREFIX . $token);
            } catch (CacheException) {
                // best-effort, non bloquant
            }
        }

        $this->cookie->remove($this->rememberCookieName());
    }

    private function resumeFromRememberCookie(): bool
    {
        $token = $this->cookie->get($this->rememberCookieName());

        if (!is_string($token) || $token === '') {
            return false;
        }

        try {
            $data = $this->cache->get(self::CACHE_PREFIX . $token);
        } catch (CacheException) {
            return false;
        }

        if (!is_array($data) || !isset($data['model'], $data['id']) || !is_string($data['model'])) {
            $this->cookie->remove($this->rememberCookieName());
            return false;
        }

        $user = $this->em->find($data['model'], $data['id']);

        if ($user === null) {
            $this->cookie->remove($this->rememberCookieName());
            return false;
        }

        $id = $this->em->getClassMetadata($user::class)->getIdentifierValue($user);

        $this->session->regenerate();
        $this->session->set(self::SESSION_KEY, $id);
        $this->session->set(self::SESSION_LAST_ACTIVITY_KEY, time());

        return true;
    }

    /**
     * @throws DatabaseException
     */
    private function findByIdentifier(mixed $value): ?object
    {
        return $this->em->getRepository($this->model)->findOneBy([$this->identifier => $value]);
    }
}
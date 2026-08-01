<?php
declare(strict_types=1);

namespace Neo\Core\Security\Auth\Guard;

use Neo\Core\Database\Exception\DatabaseException;
use Neo\Core\Database\Form\PropertyAccessor;
use Neo\Core\Database\ORM\Persistence\EntityManager;
use Neo\Core\Http\Client\Session\Session;
use Neo\Core\Security\Auth\Config\RoleConfig;
use Neo\Core\Security\Auth\Exception\AuthException;
use Neo\Core\Security\Auth\Guard\Interface\GuardInterface;
use Neo\Core\Security\Auth\PasswordManager;

final class SessionGuard implements GuardInterface
{
    private const string SESSION_KEY = '_auth_user_id';
    private const string SESSION_LAST_ACTIVITY_KEY = '_auth_last_activity';
    private const int DEFAULT_TIMEOUT = 1800;

    private readonly PropertyAccessor $accessor;

    /**
     * @param class-string $model
     */
    public function __construct(
        private readonly Session $session,
        private readonly PasswordManager $passwordManager,
        private readonly EntityManager $em,
        private readonly string $model,
        private readonly string $identifier,
        private readonly string $password,
        private readonly ?RoleConfig $role = null,
        private readonly int $timeout = self::DEFAULT_TIMEOUT,
    ) {
        $this->accessor = new PropertyAccessor();
    }

    /**
     * @param array<string, mixed> $credentials
     * @throws AuthException
     * @throws DatabaseException
     */
    public function attempt(array $credentials): bool
    {
        if (!isset($credentials[$this->identifier], $credentials[$this->password])) {
            throw new AuthException(
                title: 'Auth Error',
                message: sprintf("Credentials must contain '%s' and '%s'.", $this->identifier, $this->password),
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

        $this->login($user);

        return true;
    }

    public function login(object $user): void
    {
        $id = $this->em->getClassMetadata($user::class)->getIdentifierValue($user);

        $this->session->regenerate();
        $this->session->set(self::SESSION_KEY, $id);
        $this->session->set(self::SESSION_LAST_ACTIVITY_KEY, time());
    }

    public function logout(): void
    {
        $this->session->remove(self::SESSION_KEY);
        $this->session->remove(self::SESSION_LAST_ACTIVITY_KEY);
    }

    public function check(): bool
    {
        if (!$this->session->has(self::SESSION_KEY)) {
            return false;
        }

        $lastActivity = $this->session->get(self::SESSION_LAST_ACTIVITY_KEY, 0);

        if ((time() - $lastActivity) > $this->timeout) {
            $this->logout();
            return false;
        }

        $this->session->set(self::SESSION_LAST_ACTIVITY_KEY, time());
        return true;
    }

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

    /**
     * @throws DatabaseException
     */
    private function findByIdentifier(mixed $value): ?object
    {
        return $this->em->getRepository($this->model)->findOneBy([$this->identifier => $value]);
    }
}
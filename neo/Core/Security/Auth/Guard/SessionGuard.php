<?php
declare(strict_types=1);

namespace Neo\Core\Security\Auth\Guard;

use Neo\Core\Database\Access\Connection\DatabaseConnection;
use Neo\Core\Database\Exception\DatabaseException;
use Neo\Core\Database\ORM\Model\AbstractModel;
use Neo\Core\Http\Client\Session\Session;
use Neo\Core\Security\Auth\Exception\AuthException;
use Neo\Core\Security\Auth\Guard\Interface\GuardInterface;
use Neo\Core\Security\Auth\PasswordManager;

final class SessionGuard implements GuardInterface
{
    private const string SESSION_KEY = '_auth_user_id';
    private const string SESSION_LAST_ACTIVITY_KEY = '_auth_last_activity';
    private const int DEFAULT_TIMEOUT = 1800;

    /**
     * @param array<string, mixed> $role
     */
    public function __construct(
        private readonly Session $session,
        private readonly PasswordManager $passwordManager,
        private readonly string $model,
        private readonly string $identifier,
        private readonly string $password,
        private readonly array $role = [],
        private readonly int $timeout = self::DEFAULT_TIMEOUT,
    ) {}

    /**
     * @param array<string, mixed> $credentials
     * @throws AuthException
     * @throws DatabaseException
     */
    public function attempt(array $credentials): bool
    {
        $identifierField = $this->identifier;
        $passwordField = $this->password;

        if (!isset($credentials[$identifierField], $credentials[$passwordField])) {
            throw new AuthException(
                title: 'Auth Error',
                message: sprintf("Credentials must contain '%s' and '%s'.", $identifierField, $passwordField),
                code: 400
            );
        }

        $user = $this->findByIdentifier($credentials[$identifierField]);

        if (!$user) {
            return false;
        }

        $hashedPassword = $user->{$passwordField} ?? null;

        if (!$hashedPassword) {
            return false;
        }

        if (!$this->passwordManager->verify($credentials[$passwordField], $hashedPassword)) {
            return false;
        }

        $this->login($user);

        return true;
    }

    public function login(AbstractModel $user): void
    {
        $pk = $user::getPrimaryKey();
        $this->session->regenerate();
        $this->session->set(self::SESSION_KEY, $user->{$pk});
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

    public function user(): ?AbstractModel
    {
        if (!$this->check()) {
            return null;
        }

        $id = $this->session->get(self::SESSION_KEY);
        $modelClass = $this->model;

        $user = $modelClass::getIdentity($id);

        if ($user) {
            return $user;
        }

        $stmt = DatabaseConnection::getPdo()->prepare(
            "SELECT * FROM {$modelClass::getTable()} WHERE {$modelClass::getPrimaryKey()} = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            $this->logout();
            return null;
        }

        return new $modelClass($row);
    }

    /**
     * @throws DatabaseException
     */
    public function hasRole(string $role): bool
    {
        if (empty($this->role)) {
            return false;
        }

        $user = $this->user();

        if (!$user) {
            return false;
        }

        $foreignKey = $this->role['foreign_key'];
        $roleModelClass = $this->role['model'];
        $field = $this->role['field'];

        $roleId = $user->{$foreignKey} ?? null;

        if (!$roleId) {
            return false;
        }

        $stmt = DatabaseConnection::getPdo()->prepare(
            "SELECT * FROM {$roleModelClass::getTable()} WHERE {$roleModelClass::getPrimaryKey()} = ? LIMIT 1"
        );
        $stmt->execute([$roleId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return false;
        }

        $roleModel = new $roleModelClass($row);

        return $roleModel->{$field} === $role;
    }

    /**
     * @throws DatabaseException
     * @throws AuthException
     */
    private function findByIdentifier(mixed $value): ?AbstractModel
    {
        $modelClass = $this->model;
        $field = $this->identifier;
        $instance = new $modelClass();

        if (!empty($instance->fillable) && !in_array($field, $instance->fillable, true)) {
            throw new AuthException(
                title: 'Auth Error',
                message: "Invalid identifier field.",
                code: 400
            );
        }

        $stmt = DatabaseConnection::getPdo()->prepare(
            "SELECT * FROM {$modelClass::getTable()} WHERE {$field} = ? AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute([$value]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? new $modelClass($row) : null;
    }
}
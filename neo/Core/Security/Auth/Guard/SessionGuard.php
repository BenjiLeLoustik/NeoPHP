<?php
declare(strict_types=1);

namespace Neo\Core\Security\Auth\Guard;

use Neo\Core\Database\DatabaseConnection;
use Neo\Core\Database\ORM\Model\AbstractModel;
use Neo\Core\Error\Exception\FrameworkException;
use Neo\Core\Http\Client\Session;
use Neo\Core\Security\PasswordManager;

class SessionGuard
{
    private const SESSION_KEY = '_auth_user_id';

    private Session $session;
    private PasswordManager $passwordManager;
    private string $model;
    private string $identifier;
    private string $password;
    private string $role;

    public function __construct(
        Session $session,
        PasswordManager $passwordManager,
        string $model,
        string $identifier,
        string $password,
        string $role
    ) {
        $this->session = $session;
        $this->passwordManager = $passwordManager;
        $this->model = $model;
        $this->identifier = $identifier;
        $this->password = $password;
        $this->role = $role;
    }

    public function attempt(array $credentials): bool
    {
        $identifierField = $this->identifier;
        $passwordField   = $this->password;

        if (!isset($credentials[$identifierField], $credentials[$passwordField])) {
            throw new FrameworkException(
                title: 'Auth Error',
                message: "Les credentials doivent contenir '{$identifierField}' et '{$passwordField}'.",
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

        if (!$this->passwordManager->verify(
            $credentials[$passwordField],
            $hashedPassword
        )) {
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
    }

    public function logout(): void
    {
        $this->session->remove(self::SESSION_KEY);
    }

    public function check(): bool
    {
        return $this->session->has(self::SESSION_KEY);
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

    public function hasRole(string $role): bool
    {
        $user = $this->user();

        if (!$user || empty($this->role)) {
            return false;
        }

        $roles = $user->{$this->role} ?? null;

        if (!$roles) {
            return false;
        }

        return in_array($role, (array) $roles, true);
    }

    private function findByIdentifier(mixed $value): ?AbstractModel
    {
        $modelClass = $this->model;
        $field = $this->identifier;

        $instance = new $modelClass();

        if (!empty($instance->fillable) && !in_array($field, $instance->fillable, true)) {
            throw new FrameworkException(
                title: 'Auth Error',
                message: "Champ d'identification invalide.",
                code: 400
            );
        }

        $stmt = DatabaseConnection::getPdo()->prepare(
            "SELECT * FROM {$modelClass::getTable()} WHERE {$field} = ? LIMIT 1"
        );
        $stmt->execute([$value]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? new $modelClass($row) : null;
    }
}
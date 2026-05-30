<?php
declare(strict_types=1);

namespace Neo\Core\Security\Auth\Guard;

use Neo\Core\Database\DatabaseConnection;
use Neo\Core\Database\ORM\Model\AbstractModel;
use Neo\Core\Http\Request;
use Neo\Core\Security\Auth\Exception\AuthException;
use Neo\Core\Security\Auth\JwtManager;
use Neo\Core\Security\Auth\PasswordManager;

final class TokenGuard implements GuardInterface
{
    private ?array $payload = null;

    public function __construct(
        private Request $request,
        private JwtManager $jwtManager,
        private PasswordManager $passwordManager,
        private string $model,
        private string $identifier,
        private string $password,
        private array $role = []
    ) {}

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

        return true;
    }

    public function login(AbstractModel $user): void
    {}

    public function logout(): void
    {
        $this->payload = null;
    }

    public function check(): bool
    {
        $token = $this->extractToken();

        if (!$token) {
            return false;
        }

        return $this->jwtManager->isValid($token);
    }

    public function user(): ?AbstractModel
    {
        if (!$this->check()) {
            return null;
        }

        $payload = $this->getPayload();
        $userId = $payload['sub'] ?? null;
        $modelClass = $this->model;

        if (!$userId) {
            return null;
        }

        $stmt = DatabaseConnection::getPdo()->prepare(
            "SELECT * FROM {$modelClass::getTable()} WHERE {$modelClass::getPrimaryKey()} = ? LIMIT 1"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return new $modelClass($row);
    }

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

    public function generateToken(AbstractModel $user): string
    {
        $pk = $user::getPrimaryKey();

        return $this->jwtManager->generate([
            'sub' => $user->{$pk},
        ]);
    }

    public function getPayload(): array
    {
        if ($this->payload === null) {
            $token = $this->extractToken();

            if (!$token) {
                throw new AuthException(
                    title: 'Auth Error',
                    message: "No token found in the request.",
                    code: 401
                );
            }

            $this->payload = $this->jwtManager->decode($token);
        }

        return $this->payload;
    }

    private function extractToken(): ?string
    {
        $header = $this->request->header('Authorization');

        if (!$header || !str_starts_with($header, 'Bearer ')) {
            return null;
        }

        return substr($header, 7);
    }

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
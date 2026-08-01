<?php
declare(strict_types=1);

namespace Neo\Core\Security\Auth\Guard;

use Neo\Core\Database\Exception\DatabaseException;
use Neo\Core\Database\Form\PropertyAccessor;
use Neo\Core\Database\ORM\Persistence\EntityManager;
use Neo\Core\Http\Request\Request;
use Neo\Core\Security\Auth\Config\RoleConfig;
use Neo\Core\Security\Auth\Exception\AuthException;
use Neo\Core\Security\Auth\Exception\JwtException;
use Neo\Core\Security\Auth\Guard\Interface\GuardInterface;
use Neo\Core\Security\Auth\JwtManager;
use Neo\Core\Security\Auth\PasswordManager;

final class TokenGuard implements GuardInterface
{
    /** @var array<string, mixed>|null */
    private ?array $payload = null;

    private readonly PropertyAccessor $accessor;

    /**
     * @param class-string $model
     */
    public function __construct(
        private readonly Request $request,
        private readonly JwtManager $jwtManager,
        private readonly PasswordManager $passwordManager,
        private readonly EntityManager $em,
        private readonly string $model,
        private readonly string $identifier,
        private readonly string $password,
        private readonly ?RoleConfig $role = null,
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

        return $this->passwordManager->verify($credentials[$this->password], $hashedPassword);
    }

    public function login(object $user): void
    {
    }

    public function logout(): void
    {
        $this->payload = null;
    }

    public function check(): bool
    {
        $token = $this->extractToken();

        if ($token === null) {
            return false;
        }

        return $this->jwtManager->isValid($token);
    }

    /**
     * @throws DatabaseException
     * @throws AuthException
     * @throws JwtException
     */
    public function user(): ?object
    {
        if (!$this->check()) {
            return null;
        }

        $payload = $this->getPayload();
        $userId = $payload['sub'] ?? null;

        if ($userId === null) {
            return null;
        }

        return $this->em->find($this->model, $userId);
    }

    /**
     * @throws DatabaseException
     * @throws AuthException
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

    public function generateToken(object $user): string
    {
        $id = $this->em->getClassMetadata($user::class)->getIdentifierValue($user);

        return $this->jwtManager->generate([
            'sub' => $id,
        ]);
    }

    /**
     * @return array<string, mixed>
     * @throws AuthException
     * @throws JwtException
     */
    public function getPayload(): array
    {
        if ($this->payload === null) {
            $token = $this->extractToken();

            if ($token === null) {
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

    /**
     * @throws DatabaseException
     */
    private function findByIdentifier(mixed $value): ?object
    {
        return $this->em->getRepository($this->model)->findOneBy([$this->identifier => $value]);
    }
}
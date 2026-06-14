<?php
declare(strict_types=1);

namespace Neo\Core\Security\Auth\Guard;

use Neo\Core\Database\ORM\Model\AbstractModel;

interface GuardInterface
{
    /**
     * @param array<string, mixed> $credentials
     */
    public function attempt(array $credentials): bool;

    public function login(AbstractModel $user): void;

    public function logout(): void;

    public function check(): bool;

    public function user(): ?AbstractModel;

    public function hasRole(string $role): bool;
}
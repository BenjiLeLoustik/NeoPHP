<?php
declare(strict_types=1);

namespace Neo\Core\Security\Auth\Guard\Interface;

interface GuardInterface
{
    /**
     * @param array<string, mixed> $credentials
     */
    public function attempt(array $credentials, bool $remember = false): bool;

    public function login(object $user, bool $remember = false): void;

    public function logout(): void;

    public function check(): bool;

    public function user(): ?object;

    public function hasRole(string $role): bool;
}
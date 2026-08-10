<?php

declare(strict_types=1);

namespace Neo\Core\Security\Auth;

use Random\RandomException;

final class PasswordManager
{
    private const string DEFAULT_ALGO = PASSWORD_DEFAULT;
    private const array DEFAULT_OPTIONS = [ 'cost' => 12 ];

    public function hash(string $plainPassword): string
    {
        return password_hash($plainPassword, self::DEFAULT_ALGO, self::DEFAULT_OPTIONS);
    }

    public function verify(string $plainPassword, string $hashedPassword): bool
    {
        return password_verify($plainPassword, $hashedPassword);
    }

    public function needsRehash(string $hashedPassword): bool
    {
        return password_needs_rehash($hashedPassword, self::DEFAULT_ALGO, self::DEFAULT_OPTIONS);
    }

    /**
     * @throws RandomException
     */
    public function generate(int $length = 12): string
    {
        return random_bytes($length)
                |> bin2hex(...);
    }

    /**
     * @return array<string, mixed>
     */
    public function getInfo(string $hash): array
    {
        return password_get_info($hash);
    }
}
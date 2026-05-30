<?php

namespace Neo\Core\Security\Auth;

use Neo\Core\DI\Container;

final class PasswordManager
{
    private Container $container;
    private const DEFAULT_ALGO = PASSWORD_DEFAULT;
    private const DEFAULT_OPTIONS = [ 'cost' => 12 ];

    public function __construct(Container $container) {
        $this->container = $container;
    }

    public function hash(string $plainPassword): string
    {
        return password_hash($plainPassword, self::DEFAULT_ALGO, self::DEFAULT_OPTIONS);
    }

    public function verify(string $plainPassword, string $hashedPassword): bool
    {
        return password_verify($plainPassword, $hashedPassword);
    }

    public function needsRehash(string $plainPassword): bool
    {
        return password_needs_rehash($plainPassword, self::DEFAULT_ALGO, self::DEFAULT_OPTIONS);
    }

    public function generate(int $length = 12) {
        return bin2hex(random_bytes($length));
    }

    public function getInfo(string $hash): array
    {
        return password_get_info($hash);
    }
}
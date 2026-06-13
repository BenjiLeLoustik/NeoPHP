<?php
declare(strict_types=1);

namespace Neo\Core\Security\Csrf;

use Random\RandomException;

class CsrfTokenManager
{
    private const string SESSION_KEY = '_csrf_tokens';

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
    }

    /**
     * @throws RandomException
     */
    public function generateToken(string $id, int $expiry = 3600): CsrfToken
    {
        $value = bin2hex(random_bytes(32));
        $token = new CsrfToken($id, $value, $expiry);
        $_SESSION[self::SESSION_KEY][$id] = $token;
        return $token;
    }

    public function getToken(string $id): ?CsrfToken
    {
        return $_SESSION[self::SESSION_KEY][$id] ?? null;
    }

    public function validateToken(string $id, string $value, bool $invalidate = true): bool
    {
        $token = $this->getToken($id);
        if (!$token) return false;

        if ($token->isExpired()) {
            unset($_SESSION[self::SESSION_KEY][$id]);
            return false;
        }

        $isValid = hash_equals($token->getValue(), $value);

        if ($isValid && $invalidate) {
            unset($_SESSION[self::SESSION_KEY][$id]);
        }

        return $isValid;
    }
}

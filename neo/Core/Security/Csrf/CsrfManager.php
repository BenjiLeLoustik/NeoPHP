<?php

namespace Neo\Core\Security\Csrf;

use Neo\Core\Http\Client\Session\Session;
use Neo\Core\Http\Request;

class CsrfManager
{
    private const string SESSION_KEY = '_csrf_token';

    public function __construct(
        private readonly Session $session,
        private readonly Request $request
    ){}

    public function generate(): string
    {
        if (!$this->session->has(self::SESSION_KEY)) {
            $this->session->set(self::SESSION_KEY, bin2hex(random_bytes(32)));
        }

        return $this->session->get(self::SESSION_KEY);
    }

    public function token(): string
    {
        return $this->session->get(self::SESSION_KEY, '');
    }

    public function validate(): bool
    {
        $sessionToken = $this->session->get(self::SESSION_KEY, '');
        if ($sessionToken === '') {
            return false;
        }

        $requestToken = $this->request->body('_csrf_token')
            ?? $this->request->header('X-CSRF-Token', '');

        if (!is_string($requestToken) || $requestToken === '') {
            return false;
        }

        return hash_equals($sessionToken, $requestToken);
    }

    public function refresh(): void
    {
        $this->session->set(self::SESSION_KEY, bin2hex(random_bytes(32)));
    }
}
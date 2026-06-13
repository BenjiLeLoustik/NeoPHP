<?php
declare(strict_types=1);

namespace Neo\Core\Security;

use Neo\Core\Security\Auth\AuthManager;
use Neo\Core\Security\Csrf\CsrfTokenManager;
use Neo\Core\View\Interface\TwigExtensionInterface;

final readonly class SecurityViewExtension implements TwigExtensionInterface
{
    public function __construct(
        private AuthManager $auth,
        private CsrfTokenManager $csrf,
    ) {}

    public function getFunctions(): array
    {
        return [
            'auth_check' => [
                'callable' => fn() => $this->auth->check(),
                'options' => [],
            ],
            'auth_user' => [
                'callable' => fn() => $this->auth->user(),
                'options' => [],
            ],
            'auth_has_role' => [
                'callable' => fn(string $role) => $this->auth->hasRole($role),
                'options' => [],
            ],
            'csrf_token' => [
                'callable' => fn(string $id = 'default') => $this->csrf->generateToken($id)->getValue(),
                'options' => [],
            ],
        ];
    }

    public function getFilters(): array
    {
        return [];
    }
}
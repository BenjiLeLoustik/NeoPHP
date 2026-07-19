<?php
declare(strict_types=1);

namespace Neo\Core\Security\Extension;

use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\Security\Auth\AuthManager;
use Neo\Core\Security\Csrf\CsrfTokenManager;
use Neo\Core\View\Interface\TwigExtensionInterface;

#[Extension(type: ExtensionTypeEnum::VIEW)]
final readonly class SecurityViewExtension implements TwigExtensionInterface
{
    public function __construct(
        private AuthManager $auth,
        private CsrfTokenManager $csrf,
    ) {}

    /**
     * @return array<string, array{callable: callable, options: array<string, mixed>}>
     */
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
                'callable' => fn(string $id = 'default') => (
                    $this->csrf->getToken($id) ?? $this->csrf->generateToken($id)
                )->getValue(),
                'options' => [],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getFilters(): array
    {
        return [];
    }
}
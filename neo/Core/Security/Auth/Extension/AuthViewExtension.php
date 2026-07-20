<?php
declare(strict_types=1);

namespace Neo\Core\Security\Auth\Extension;

use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\Security\Auth\AuthManager;
use Neo\Core\View\Interface\TwigExtensionInterface;

#[Extension(type: ExtensionTypeEnum::VIEW)]
final readonly class AuthViewExtension implements TwigExtensionInterface
{
    public function __construct(
        private AuthManager $auth,
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
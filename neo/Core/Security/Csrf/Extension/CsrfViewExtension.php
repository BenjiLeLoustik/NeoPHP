<?php
declare(strict_types=1);

namespace Neo\Core\Security\Csrf\Extension;

use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\Security\Csrf\CsrfTokenManager;
use Neo\Core\View\Interface\TwigExtensionInterface;

#[Extension(type: ExtensionTypeEnum::VIEW)]
final readonly class CsrfViewExtension implements TwigExtensionInterface
{
    public function __construct(
        private CsrfTokenManager $csrf,
    ) {}

    /**
     * @return array<string, array{callable: callable, options: array<string, mixed>}>
     */
    public function getFunctions(): array
    {
        return [
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
<?php
declare(strict_types=1);

namespace Neo\Core\Http\Client\Flash\Extension;

use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\Http\Client\Flash\Flash;
use Neo\Core\View\Interface\TwigExtensionInterface;

#[Extension(type: ExtensionTypeEnum::VIEW)]
final readonly class FlashViewExtension implements TwigExtensionInterface
{
    public function __construct(private Flash $flash) {}

    /**
     * @return array<string, array{
     *     callable: callable,
     *     options: array<string, array<int, string>>
     * }>
     */
    public function getFunctions(): array
    {
        return [
            'flashes' => [
                'callable' => fn() => $this->flash->render(),
                'options' => ['is_safe' => ['html']],
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
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
            'flashes_type' => [
                'callable' => fn(string $type) => $this->flash->get($type),
                'options' => [],
            ],
            'flashes_raw' => [
                'callable' => fn() => $this->flash->getAll(),
                'options' => [],
            ],
            'has_flashes' => [
                'callable' => fn(?string $type = null) => $this->flash->has($type),
                'options' => [],
            ]
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
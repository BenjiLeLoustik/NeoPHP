<?php
declare(strict_types=1);

namespace Neo\Core\Asset;

use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\View\Interface\TwigExtensionInterface;

#[Extension(type: ExtensionTypeEnum::VIEW)]
final readonly class AssetViewExtension implements TwigExtensionInterface
{
    public function __construct(private AssetManager $handler) {}

    /**
     * @return array<string, array{callable: \Closure, options: array<string, mixed>}>
     */
    public function getFunctions(): array
    {
        return [
            'asset' => [
                'callable' => fn(string $path) => $this->handler->getAssetPath($path),
                'options' => [],
            ],
        ];
    }

    /**
     * @return array<string, array{callable: \Closure, options: array<string, mixed>}>
     */
    public function getFilters(): array
    {
        return [];
    }
}
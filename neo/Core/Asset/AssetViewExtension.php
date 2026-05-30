<?php
declare(strict_types=1);

namespace Neo\Core\Asset;

use Neo\Core\View\Interface\TwigExtensionInterface;

final class AssetViewExtension implements TwigExtensionInterface
{
    public function __construct(private readonly AssetHandler $handler) {}

    public function getFunctions(): array
    {
        return [
            'asset' => [
                'callable' => fn(string $path) => $this->handler->getAssetPath($path),
                'options' => [],
            ],
        ];
    }

    public function getFilters(): array
    {
        return [];
    }
}
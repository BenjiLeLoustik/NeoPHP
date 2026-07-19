<?php
declare(strict_types=1);

namespace Neo\Core\Routing\Extension;

use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\Routing\RouterManager;
use Neo\Core\View\Interface\TwigExtensionInterface;

#[Extension(type: ExtensionTypeEnum::VIEW)]
final readonly class RouterViewExtension implements TwigExtensionInterface
{
    public function __construct(private RouterManager $router) {}

    /**
     * @return array<string, array{callable: callable, options: array<string, mixed>}>
     */
    public function getFunctions(): array
    {
        return [
            'path' => [
                'callable' => fn(string $name, array $params = []) => $this->router->generateUrl($name, $params),
                'options' => [],
            ],
            'currentRoute' => [
                'callable' => fn() => $this->router->getCurrentRouteName(),
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
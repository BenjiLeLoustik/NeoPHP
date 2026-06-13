<?php
declare(strict_types=1);

namespace Neo\Core\Routing;

use Neo\Core\View\Interface\TwigExtensionInterface;

final readonly class RouterViewExtension implements TwigExtensionInterface
{
    public function __construct(private Router $router) {}

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

    public function getFilters(): array
    {
        return [];
    }
}
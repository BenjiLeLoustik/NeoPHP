<?php
declare(strict_types=1);

namespace Neo\Core\Http\Client\Cookie\Extension;

use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\Http\Client\Cookie\Cookie;
use Neo\Core\View\Interface\TwigExtensionInterface;

#[Extension(type: ExtensionTypeEnum::VIEW)]
final readonly class CookieViewExtension implements TwigExtensionInterface
{
    public function __construct(private Cookie $cookie) {}

    /**
     * @return array<string, array{
     *     callable: callable,
     *     options: array<string, array<int, string>>
     * }>
     */
    public function getFunctions(): array
    {
        return [
            'cookie' => [
                'callable' => fn(string $name, mixed $default = null) => $this->cookie->get($name, $default),
                'options' => [],
            ],
            'has_cookie' => [
                'callable' => fn(string $name) => $this->cookie->has($name),
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
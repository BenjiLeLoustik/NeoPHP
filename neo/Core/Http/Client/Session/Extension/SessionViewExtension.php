<?php
declare(strict_types=1);

namespace Neo\Core\Http\Client\Session\Extension;

use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\Http\Client\Session\Session;
use Neo\Core\View\Interface\TwigExtensionInterface;

#[Extension(type: ExtensionTypeEnum::VIEW)]
final readonly class SessionViewExtension implements TwigExtensionInterface
{
    public function __construct(private Session $session) {}

    /**
     * @return array<string, array{
     *     callable: callable,
     *     options: array<string, array<int, string>>
     * }>
     */
    public function getFunctions(): array
    {
        return [
            'session' => [
                'callable' => fn(string $key, mixed $default = null) => $this->session->get($key, $default),
                'options' => [],
            ],
            'has_session' => [
                'callable' => fn(string $key) => $this->session->has($key),
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
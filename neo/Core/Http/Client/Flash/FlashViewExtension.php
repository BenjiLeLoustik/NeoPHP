<?php
declare(strict_types=1);

namespace Neo\Core\Http\Client\Flash;

use Neo\Core\View\Interface\TwigExtensionInterface;
use Twig\Markup;

final class FlashViewExtension implements TwigExtensionInterface
{
    public function __construct(private readonly Flash $flash) {}

    public function getFunctions(): array
    {
        return [
            'flashes' => [
                'callable' => fn() => $this->flash->render(),
                'options' => ['is_safe' => ['html']],
            ],
        ];
    }

    public function getFilters(): array
    {
        return [];
    }
}
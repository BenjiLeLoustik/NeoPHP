<?php
declare(strict_types=1);

namespace Neo\Core\Database\Pagination\Extension;

use Neo\Core\Database\Pagination\Paginator;
use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\Http\Request\Request;
use Neo\Core\View\Interface\TwigExtensionInterface;

#[Extension(type: ExtensionTypeEnum::VIEW)]
final readonly class PaginationViewExtension implements TwigExtensionInterface
{
    public function __construct(
        private Request $request,
    ) {
    }

    /**
     * @return array<string, array{callable: \Closure, options: array<string, mixed>}>
     */
    public function getFunctions(): array
    {
        return [
            'pagination_url' => [
                'callable' => fn (?string $baseUrl, int $page) => $this->request->buildUrlWithParams(['page' => $page], $baseUrl),
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
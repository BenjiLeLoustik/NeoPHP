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
            'paginator_links' => [
                'callable' => fn (
                    Paginator $paginator,
                    ?string $baseUrl = null,
                    string $prevLabel = '&laquo;',
                    string $nextLabel = '&raquo;',
                    string $gapLabel = '&hellip;'
                ) => $this->render($paginator, $baseUrl, $prevLabel, $nextLabel, $gapLabel),
                'options' => ['is_safe' => ['html']],
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

    /**
     * @param Paginator<mixed> $paginator
     */
    private function render(Paginator $paginator, ?string $baseUrl, string $prevLabel, string $nextLabel, string $gapLabel): string
    {
        $baseUrl ??= $this->request->getPath();

        if ($paginator->getTotalPages() <= 1) {
            return '';
        }

        $html = '<nav class="pagination">';

        if ($paginator->hasPreviousPage()) {
            $html .= sprintf(
                '<a href="%s" rel="prev">%s</a>',
                $this->buildUrl($baseUrl, (int) $paginator->getPreviousPage()),
                $prevLabel
            );
        }

        foreach ($paginator->getLinks() as $page) {
            if ($page === null) {
                $html .= sprintf('<span class="pagination-gap">%s</span>', $gapLabel);
                continue;
            }

            $isCurrent = $page === $paginator->getCurrentPage();
            $html .= sprintf(
                '<a href="%s"%s>%d</a>',
                $this->buildUrl($baseUrl, $page),
                $isCurrent ? ' class="active" aria-current="page"' : '',
                $page
            );
        }

        if ($paginator->hasNextPage()) {
            $html .= sprintf(
                '<a href="%s" rel="next">%s</a>',
                $this->buildUrl($baseUrl, (int) $paginator->getNextPage()),
                $nextLabel
            );
        }

        $html .= '</nav>';

        return $html;
    }

    private function buildUrl(string $baseUrl, int $page): string
    {
        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return htmlspecialchars($baseUrl . $separator . 'page=' . $page, ENT_QUOTES, 'UTF-8');
    }
}
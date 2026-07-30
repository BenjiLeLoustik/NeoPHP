<?php

namespace Neo\Core\Tools\Markdown\Extension;

use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\Tools\Markdown\MarkdownManager;
use Neo\Core\View\Interface\TwigExtensionInterface;

#[Extension(type: ExtensionTypeEnum::VIEW)]
class MarkdownViewExtension implements TwigExtensionInterface
{

    public function __construct(
        private MarkdownManager $markdownManager,
    ) {
    }

    /**
     * @return array<string, array{callable: \Closure, options: array<string, mixed>}>
     */
    public function getFunctions(): array
    {
        return [
            'markdown_blocks' => [
                'callable' => fn(string $input) => $this->markdownManager->blocks($input),
                'options' => []
            ]
        ];
    }

    /**
     * @return array<string, array{callable: \Closure, options: array<string, mixed>}>
     */
    public function getFilters(): array
    {
        return [
            'md_inline' => [
                'callable' => fn(string $text) => $this->markdownManager->renderInline($text),
                'options' => ['is_safe' => ['html']]
            ]
        ];
    }
}
<?php

declare(strict_types=1);

namespace Neo\Core\Tools\Markdown\Block;

class QuoteBlock extends AbstractBlock
{
    public function __construct(
        public readonly string $content,
    ) {
        parent::__construct('quote');
    }

    public function getContent(): string
    {
        return $this->content;
    }
}
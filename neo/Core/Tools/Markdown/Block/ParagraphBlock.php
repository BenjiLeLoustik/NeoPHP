<?php

declare(strict_types=1);

namespace Neo\Core\Tools\Markdown\Block;

class ParagraphBlock extends AbstractBlock
{
    public function __construct(
        public string $text,
    ) {
        parent::__construct('paragraph');
    }

    public function getText(): string
    {
        return $this->text;
    }
}
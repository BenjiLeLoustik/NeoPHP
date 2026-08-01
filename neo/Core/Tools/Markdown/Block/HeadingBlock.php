<?php

namespace Neo\Core\Tools\Markdown\Block;

class HeadingBlock extends AbstractBlock
{
    public function __construct(
        public int $level,
        public string $text,
        public string $slug,
    ) {
        parent::__construct('heading');
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }
}
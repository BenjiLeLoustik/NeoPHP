<?php

namespace Neo\Core\Tools\Markdown\Block;

class CodeBlock extends AbstractBlock
{
    public function __construct(
        public string $language,
        public string $content,
    ) {
        parent::__construct('code');
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getContent(): string
    {
        return $this->content;
    }
}
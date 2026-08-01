<?php

declare(strict_types=1);

namespace Neo\Core\Tools\Markdown\Block;

abstract class AbstractBlock
{
    public function __construct(
        public string $type
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }
}
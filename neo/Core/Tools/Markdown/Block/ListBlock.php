<?php

declare(strict_types=1);

namespace Neo\Core\Tools\Markdown\Block;

class ListBlock extends AbstractBlock
{
    /**
     * @param list<string> $items
     */
    public function __construct(
        public bool $ordered,
        public array $items,
    ) {
        parent::__construct('list');
    }

    public function getOrdered(): bool
    {
        return $this->ordered;
    }

    /**
     * @return list<string> $items
     */
    public function getItems(): array
    {
        return $this->items;
    }
}
<?php

namespace Neo\Core\Tools\Markdown\Block;

class ListBlock extends AbstractBlock
{
    /**
     * @param list<string> $items
     */
    public function __construct(
        public readonly bool $ordered,
        public readonly array $items,
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
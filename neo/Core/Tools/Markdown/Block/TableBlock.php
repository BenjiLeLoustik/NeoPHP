<?php

namespace Neo\Core\Tools\Markdown\Block;

class TableBlock extends AbstractBlock
{
    /**
     * @param list<string> $header
     * @param list<list<string>> $rows
     */
    public function __construct(
        public readonly array $header,
        public readonly array $rows,
    ) {
        parent::__construct('table');
    }

    /**
     * @return list<string>
     */
    public function getHeader(): array
    {
        return $this->header;
    }

    /**
     * @return list<list<string>>
     */
    public function getRows(): array
    {
        return $this->rows;
    }

}
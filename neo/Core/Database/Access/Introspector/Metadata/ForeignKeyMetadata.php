<?php
declare(strict_types=1);

namespace Neo\Core\Database\Access\Introspector\Metadata;

class ForeignKeyMetadata
{
    public function __construct(
        private string $name,
        private string $column,
        private string $referencedTable,
        private string $referencedColumn,
        private string $onDelete,
        private string $onUpdate,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getColumn(): string
    {
        return $this->column;
    }

    public function getReferencedTable(): string
    {
        return $this->referencedTable;
    }

    public function getReferencedColumn(): string
    {
        return $this->referencedColumn;
    }

    public function getOnDelete(): string
    {
        return $this->onDelete;
    }

    public function getOnUpdate(): string
    {
        return $this->onUpdate;
    }

    /**
     * @return array{
     *     name: string,
     *     column: string,
     *     referencedTable: string,
     *     referencedColumn: string,
     *     onDelete: string,
     *     onUpdate: string
     * }
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'column' => $this->column,
            'referencedTable' => $this->referencedTable,
            'referencedColumn' => $this->referencedColumn,
            'onDelete' => $this->onDelete,
            'onUpdate' => $this->onUpdate,
        ];
    }
}
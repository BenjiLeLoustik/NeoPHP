<?php
declare(strict_types=1);

namespace Neo\Core\Database\Access\Introspector\Metadata;

class IndexMetadata
{
    /**
     * @param list<string> $columns
     */
    public function __construct(
        private string $name,
        private array $columns,
        private bool $unique,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return list<string>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    public function isUnique(): bool
    {
        return $this->unique;
    }

    /**
     * @return array{name: string, columns: list<string>, unique: bool}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'columns' => $this->columns,
            'unique' => $this->unique,
        ];
    }
}
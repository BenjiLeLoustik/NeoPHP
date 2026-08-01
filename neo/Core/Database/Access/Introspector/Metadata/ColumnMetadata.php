<?php
declare(strict_types=1);

namespace Neo\Core\Database\Access\Introspector\Metadata;

class ColumnMetadata
{
    public function __construct(
        private string $name,
        private string $type,
        private bool $nullable,
        private ?string $default,
        private string $key,
        private string $extra,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    public function getDefault(): ?string
    {
        return $this->default;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getExtra(): string
    {
        return $this->extra;
    }

    /**
     * @return array{name: string, type: string, nullable: bool, default: string|null, key: string, extra: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'nullable' => $this->nullable,
            'default' => $this->default,
            'key' => $this->key,
            'extra' => $this->extra,
        ];
    }
}
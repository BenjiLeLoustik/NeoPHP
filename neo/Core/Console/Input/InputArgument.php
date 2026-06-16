<?php
declare(strict_types=1);

namespace Neo\Core\Console\Input;

final class InputArgument
{
    public const int REQUIRED = 1;
    public const int OPTIONAL = 2;
    public const int IS_ARRAY = 4;

    private int $mode;
    private mixed $default;

    public function __construct(
        private readonly string $name,
        private readonly string $description = '',
        int $mode = self::OPTIONAL,
        mixed $default = null,
    ) {
        $this->mode = $mode;
        $this->default = $default;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function isRequired(): bool
    {
        return (bool) ($this->mode & self::REQUIRED);
    }

    public function isArray(): bool
    {
        return (bool) ($this->mode & self::IS_ARRAY);
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    public function getMode(): int
    {
        return $this->mode;
    }

    public function getModeLabel(): string
    {
        $parts = [];

        if ($this->isRequired()) {
            $parts[] = 'required';
        } else {
            $parts[] = 'optional';
        }

        if ($this->isArray()) {
            $parts[] = 'array';
        }

        return implode(', ', $parts);
    }
}
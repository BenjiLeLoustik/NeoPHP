<?php
declare(strict_types=1);

namespace Neo\Core\Console\Input;

final class InputOption
{
    public const int NONE = 1;
    public const int REQUIRED = 2;
    public const int OPTIONAL = 4;
    public const int IS_ARRAY = 8;

    private int $mode;
    private mixed $default;

    public function __construct(
        private readonly string $name,
        private readonly ?string $shortcut = null,
        int $mode = self::NONE,
        private readonly string $description = '',
        mixed $default = null,
    ) {
        $this->mode = $mode;
        $this->default = $default;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getShortcut(): ?string
    {
        return $this->shortcut;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function isFlag(): bool
    {
        return (bool) ($this->mode & self::NONE);
    }

    public function requiresValue(): bool
    {
        return (bool) ($this->mode & self::REQUIRED);
    }

    public function isValueOptional(): bool
    {
        return (bool) ($this->mode & self::OPTIONAL);
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

    public function getSynopsis(): string
    {
        $base = '--' . $this->name;

        if ($this->shortcut !== null) {
            $base = '-' . $this->shortcut . ', ' . $base;
        }

        if ($this->requiresValue()) {
            $base .= '=<' . $this->name . '>';
        } elseif ($this->isValueOptional()) {
            $base .= '[=<' . $this->name . '>]';
        }

        return $base;
    }
}
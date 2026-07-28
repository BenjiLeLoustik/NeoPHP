<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form\Field;

use Neo\Core\Validator\Abstract\AbstractConstraint;

final class FormField
{
    private mixed $value = null;

    /** @var list<string> */
    private array $errors = [];

    /**
     * @param list<AbstractConstraint> $constraints
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly string $name,
        private readonly FieldType $type,
        private readonly string $label,
        private readonly array $constraints = [],
        private readonly array $options = [],
        private readonly bool $mapped = true,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): FieldType
    {
        return $this->type;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @return list<AbstractConstraint>
     */
    public function getConstraints(): array
    {
        return $this->constraints;
    }

    /**
     * @return list<class-string>
     */
    public function getRemovedConstraints(): array
    {
        return array_values((array) ($this->options['removeConstraints'] ?? []));
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function getOption(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    public function isMapped(): bool
    {
        return $this->mapped;
    }

    public function isRequired(): bool
    {
        return (bool) ($this->options['required'] ?? false);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getChoices(): array
    {
        return (array) ($this->options['choices'] ?? []);
    }

    public function setValue(mixed $value): void
    {
        $this->value = $value;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function addError(string $error): void
    {
        $this->errors[] = $error;
    }

    /**
     * @return list<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function clearErrors(): void
    {
        $this->errors = [];
    }
}
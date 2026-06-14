<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form;

use Neo\Core\Database\Form\Type\AbstractType;
use Neo\Core\Database\Form\Type\CollectionType;
use ReflectionClass;

class FormField
{
    private string $name;

    private AbstractType $type;

    /** @var array<string, mixed> */
    private array $options;

    /** @var array<int, string> */
    private array $errors = [];

    private mixed $value = null;

    /** @var array<string, string> */
    private array $wrapperAttributes = [];

    /** @var array<string, array<int, string>> */
    private array $collectionErrors = [];

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(string $name, AbstractType $type, array $options = [])
    {
        $this->name = $name;
        $this->type = $type;
        $this->options = $options;
        $this->value = $options['value'] ?? null;
    }

    public function __get(string $key): mixed
    {
        $method = 'get' . ucfirst($key);
        if (method_exists($this, $method)) {
            return $this->$method();
        }

        return $this->options[$key] ?? null;
    }

    public function __isset(string $key): bool
    {
        return method_exists($this, 'get' . ucfirst($key)) || isset($this->options[$key]);
    }

    public function getId(): string
    {
        return 'field_' . $this->name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): AbstractType
    {
        return $this->type;
    }

    public function getTypeName(): string
    {
        return $this->type->type;
    }

    /**
     * @param array<string, mixed> $choices
     */
    public function setChoices(array $choices): void
    {
        $this->options['choices'] = $choices;
    }

    /**
     * @return array<string, mixed>
     */
    public function getChoices(): array
    {
        return $this->options['choices'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public function setOption(string $key, mixed $value): void
    {
        $this->options[$key] = $value;

        if ($key === 'value') {
            $this->value = $value;
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
        $this->value = $options['value'] ?? null;
    }

    public function getOption(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function setValue(mixed $value): void
    {
        $this->value = $value;
    }

    public function getLabel(): string
    {
        return $this->options['label'] ?? ucfirst($this->name);
    }

    /**
     * @param array<int, string> $errors
     */
    public function setErrors(array $errors): void
    {
        $this->errors = $errors;
    }

    /**
     * @return array<int, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function addError(string $message): void
    {
        $this->errors[] = $message;
    }

    public function render(): string
    {
        return $this->type->render($this);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function withOptions(array $options): self
    {
        $clone = clone $this;

        if (isset($options['attrs']) && is_array($options['attrs'])) {
            $clone->options['attrs'] = array_merge(
                $clone->getAttributes(),
                $options['attrs']
            );
            unset($options['attrs']);
        }

        if (isset($options['attr']) && is_array($options['attr'])) {
            $clone->options['attr'] = array_merge(
                is_array($clone->getOption('attr', [])) ? $clone->getOption('attr', []) : [],
                $options['attr']
            );
            unset($options['attr']);
        }

        foreach ($options as $key => $value) {
            $clone->options[$key] = $value;
        }

        if (array_key_exists('value', $clone->options)) {
            $clone->value = $clone->options['value'];
        }

        return $clone;
    }

    /**
     * @param array<string, string> $attrs
     */
    public function setWrapperAttributes(array $attrs): void
    {
        $this->wrapperAttributes = $attrs;
    }

    public function addWrapperAttribute(string $key, string $value): void
    {
        $this->wrapperAttributes[$key] = $value;
    }

    /**
     * @return array<string, string>
     */
    public function getWrapperAttributes(): array
    {
        return $this->wrapperAttributes;
    }

    /**
     * @return array<string, string>
     */
    public function getAttributes(): array
    {
        return $this->options['attrs'] ?? [];
    }

    public function addCollectionError(string $key, string $message): void
    {
        $this->collectionErrors[$key][] = $message;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function getCollectionErrors(): array
    {
        return $this->collectionErrors;
    }

    public function hasCollectionErrors(): bool
    {
        return !empty($this->collectionErrors);
    }

    public function getAllowAdd(): bool
    {
        return $this->type instanceof CollectionType
            ? $this->type->getAllowAdd($this)
            : false;
    }

    public function getAllowDelete(): bool
    {
        return $this->type instanceof CollectionType
            ? $this->type->getAllowDelete($this)
            : false;
    }

    /**
     * @return array<int, mixed>
     */
    public function getEntries(): array
    {
        return $this->type instanceof CollectionType
            ? $this->type->getEntries($this)
            : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getEntryFields(): array
    {
        return $this->type instanceof CollectionType
            ? $this->type->getEntryFields($this)
            : [];
    }

    public function getPrototype(): string
    {
        return $this->type instanceof CollectionType
            ? $this->type->getPrototype($this)
            : '';
    }

    public function resetCollectionErrors(): void
    {
        $this->collectionErrors = [];
    }

    /**
     * @param array<string, string> $attrs
     */
    public function setAtrributes(array $attrs): void
    {
        $this->options['attrs'] = array_merge(
            $this->options['attrs'] ?? [],
            $attrs
        );
    }
}

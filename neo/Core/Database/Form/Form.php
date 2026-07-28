<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form;

use Neo\Core\Database\Form\Field\FieldType;
use Neo\Core\Database\Form\Field\FormField;
use Neo\Core\Security\Csrf\CsrfManager;
use Neo\Core\Validator\ValidatorManager;
use stdClass;

final class Form
{
    public const string CSRF_FIELD = '_csrf_token';

    private array $fields = [];

    private bool $submitted = false;

    public function __construct(
        private readonly string $name,
        private readonly PropertyAccessor $accessor,
        private readonly ValidatorManager $validator,
        private readonly ?CsrfManager $csrf = null,
        private ?object $entity = null,
    ) {}

    public function add(FormField $field): self
    {
        $this->fields[$field->getName()] = $field;
        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->fields[$name]);
    }

    public function get(string $name): ?FormField
    {
        return $this->fields[$name] ?? null;
    }

    public function getField(string $name): ?FormField
    {
        return $this->fields[$name] ?? null;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEntity(): ?object
    {
        return $this->entity;
    }

    public function getAddedConstraints(string $name): array
    {
        return $this->getField($name)?->getConstraints() ?? [];
    }

    public function isConstraintRemoved(string $name, string $constraintClass): bool
    {
        $field = $this->getField($name);
        return $field !== null && in_array($constraintClass, $field->getRemovedConstraints(), true);
    }

    public function isCsrfProtected(): bool
    {
        return $this->csrf !== null;
    }

    public function getCsrfToken(): string
    {
        return $this->csrf?->generate() ?? '';
    }

    public function bind(object $entity): self
    {
        $this->entity = $entity;

        foreach ($this->fields as $field) {
            if ($field->isMapped() && $field->getName() !== self::CSRF_FIELD) {
                $field->setValue($this->accessor->getValue($entity, $field->getName()));
            }
        }

        return $this;
    }

    public function handleRequest(array $data): self
    {
        $this->submitted = true;

        foreach ($this->fields as $field) {
            if ($field->getName() === self::CSRF_FIELD) {
                continue;
            }
            if ($field->getType() === FieldType::Checkbox) {
                $field->setValue(!empty($data[$field->getName()]));
                continue;
            }
            $field->setValue($data[$field->getName()] ?? null);
        }

        if ($this->entity !== null) {
            $this->mapToEntity();
        }

        $this->clearErrors();

        if ($this->csrf !== null && !$this->csrf->validate()) {
            ($this->getField(self::CSRF_FIELD) ?? $this->firstField())?->addError('Invalid CSRF token.');
        }

        $model = $this->entity ?? new stdClass();
        foreach ($this->validator->validate($model, $this) as $fieldName => $messages) {
            $field = $this->getField($fieldName);
            if ($field === null) {
                continue;
            }
            foreach ($messages as $message) {
                if ($message !== '') {
                    $field->addError($message);
                }
            }
        }

        return $this;
    }

    public function isSubmitted(): bool
    {
        return $this->submitted;
    }

    public function isValid(): bool
    {
        if (!$this->submitted) {
            return false;
        }

        foreach ($this->fields as $field) {
            if ($field->hasErrors()) {
                return false;
            }
        }

        return true;
    }

    public function getErrors(): array
    {
        $errors = [];
        foreach ($this->fields as $name => $field) {
            if ($field->hasErrors()) {
                $errors[$name] = $field->getErrors();
            }
        }
        return $errors;
    }

    public function getData(): array
    {
        $data = [];
        foreach ($this->fields as $name => $field) {
            $data[$name] = $field->getValue();
        }
        return $data;
    }

    private function mapToEntity(): void
    {
        foreach ($this->fields as $field) {
            if (!$field->isMapped() || $field->getName() === self::CSRF_FIELD) {
                continue;
            }
            $this->accessor->setValue($this->entity, $field->getName(), $field->getValue());
        }
    }

    private function clearErrors(): void
    {
        foreach ($this->fields as $field) {
            $field->clearErrors();
        }
    }

    private function firstField(): ?FormField
    {
        foreach ($this->fields as $field) {
            return $field;
        }
        return null;
    }
}
<?php
declare(strict_types=1);

namespace Neo\Core\Validator;

use Neo\Core\Database\Form\Form;
use ReflectionObject;

final class ValidationContext
{
    /** @var list<string> */
    private array $violations = [];

    public function __construct(
        private string $field,
        private object $model,
        private ?Form $form = null,
    ) {
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getModel(): object
    {
        return $this->model;
    }

    public function getForm(): ?Form
    {
        return $this->form;
    }

    public function addViolation(string $message): void
    {
        $this->violations[] = $message;
    }

    /**
     * @return list<string>
     */
    public function getViolations(): array
    {
        return $this->violations;
    }

    public function hasViolations(): bool
    {
        return $this->violations !== [];
    }

    public function fieldExists(string $field): bool
    {
        $ref = new ReflectionObject($this->model);
        if ($ref->hasProperty($field)) {
            return true;
        }
        return $this->form !== null && $this->form->getField($field) !== null;
    }

    public function getValue(string $field): mixed
    {
        $ref = new ReflectionObject($this->model);
        if ($ref->hasProperty($field)) {
            $prop = $ref->getProperty($field);
            return $prop->isInitialized($this->model)
                ? $prop->getValue($this->model)
                : null;
        }

        if ($this->form !== null && $this->form->getField($field) !== null) {
            return $this->form->getField($field)->getValue();
        }

        return null;
    }
}
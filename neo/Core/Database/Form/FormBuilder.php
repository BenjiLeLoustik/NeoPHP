<?php
declare(strict_types=1);

namespace Neo\Core\Database\Form;

use Neo\Core\Database\Form\Field\FieldType;
use Neo\Core\Database\Form\Field\FormField;
use Neo\Core\Security\Csrf\CsrfManager;
use Neo\Core\Validator\Assert\Length;
use Neo\Core\Validator\Assert\NotBlank;
use Neo\Core\Validator\ValidatorManager;

final class FormBuilder
{
    /** @var array<string, array{type: string, options: array<string, mixed>}> */
    private array $definitions = [];

    public function __construct(
        private readonly string $name,
        private readonly PropertyAccessor $accessor,
        private readonly ValidatorManager $validator,
        private readonly ?CsrfManager $csrf = null,
        private readonly ?object $entity = null,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function add(string $name, string $type = 'text', array $options = []): self
    {
        $this->definitions[$name] = ['type' => $type, 'options' => $options];
        return $this;
    }

    public function remove(string $name): self
    {
        unset($this->definitions[$name]);
        return $this;
    }

    public function getForm(): Form
    {
        $form = new Form($this->name, $this->accessor, $this->validator, $this->csrf, $this->entity);

        foreach ($this->definitions as $name => $definition) {
            $form->add($this->buildField($name, $definition['type'], $definition['options']));
        }

        if ($this->csrf !== null) {
            $form->add(new FormField(
                name: Form::CSRF_FIELD,
                type: FieldType::Hidden,
                label: '',
                constraints: [],
                options: [],
                mapped: false,
            ));
        }

        if ($this->entity !== null) {
            $form->bind($this->entity);
        }

        if ($this->csrf !== null) {
            $form->getField(Form::CSRF_FIELD)?->setValue($form->getCsrfToken());
        }

        return $form;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function buildField(string $name, string $type, array $options): FormField
    {
        $fieldType = FieldType::fromName($type);
        $constraints = $options['constraints'] ?? [];

        if (!empty($options['required'])) {
            $constraints[] = new NotBlank($options['requiredMessage'] ?? 'This field is required.');
        }

        if (isset($options['minLength']) || isset($options['maxLength'])) {
            $constraints[] = new Length(
                min: $options['minLength'] ?? null,
                max: $options['maxLength'] ?? null,
                message: $options['lengthMessage'] ?? 'This value has an invalid length.',
            );
        }

        return new FormField(
            name: $name,
            type: $fieldType,
            label: $options['label'] ?? $this->humanize($name),
            constraints: $constraints,
            options: $options,
            mapped: $options['mapped'] ?? true,
        );
    }

    private function humanize(string $name): string
    {
        return ucfirst(strtolower(trim((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $name))));
    }
}
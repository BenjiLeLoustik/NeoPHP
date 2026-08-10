<?php

declare(strict_types=1);

namespace Neo\Core\Validator;

use Neo\Core\Database\Form\Form;
use Neo\Core\DI\Container;
use Neo\Core\Validator\Assert\NotBlank;
use Neo\Core\Validator\Interface\ConstraintInterface;
use Neo\Core\Validator\Interface\ConstraintValidatorInterface;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;

class ValidatorManager
{
    /** @var array<class-string, ConstraintValidatorInterface> */
    private array $validators = [];

    /** @var list<array{
     *     model: class-string,
     *     field: string,
     *     constraint: class-string,
     *     value: string,
     *     passed: bool,
     *     message: string|null,
     *     duration: float
     * }>
     */
    private array $log = [];

    public function __construct(
        private Container $container,
    ) {
    }

    /**
     * @return array<string, list<string>>
     */
    public function validate(object $model, ?Form $form = null): array
    {
        $errors = [];
        $handled = [];

        $refClass = new ReflectionClass($model);

        foreach ($refClass->getProperties() as $prop) {
            $field = $prop->getName();
            $handled[$field] = true;

            $value = $prop->isInitialized($model) ? $prop->getValue($model) : null;

            $constraints = $this->attributeConstraints($prop);
            if ($form !== null) {
                $constraints = array_merge($constraints, $form->getAddedConstraints($field));
            }

            $this->runField($field, $value, $constraints, $model, $form, $errors);
        }

        if ($form !== null) {
            foreach ($form->getFields() as $formField) {
                $field = $formField->getName();
                if (isset($handled[$field])) {
                    continue;
                }

                $this->runField(
                    $field,
                    $formField->getValue(),
                    $form->getAddedConstraints($field),
                    $model,
                    $form,
                    $errors
                );
            }
        }

        return $errors;
    }

    /**
     * @param list<ConstraintInterface> $constraints
     * @param array<string, list<string>> $errors
     */
    private function runField(
        string $field,
        mixed $value,
        array $constraints,
        object $model,
        ?Form $form,
        array &$errors
    ): void {
        $isEmpty = $value === null || $value === '';

        foreach ($constraints as $constraint) {
            if ($form !== null && $form->isConstraintRemoved($field, $constraint::class)) {
                continue;
            }
            if ($isEmpty && !$constraint->runOnEmpty()) {
                continue;
            }

            $start = microtime(true);
            $context = new ValidationContext($field, $model, $form);
            $this->resolveValidator($constraint->validatedBy())->validate($value, $constraint, $context);
            $duration = round((microtime(true) - $start) * 1000, 2);

            $violations = $context->getViolations();

            $this->log[] = [
                'model' => $model::class,
                'field' => $field,
                'constraint' => $constraint::class,
                'value' => $this->stringifyValue($value),
                'passed' => $violations === [],
                'message' => $violations !== [] ? implode(' ', $violations) : null,
                'duration' => $duration,
            ];

            foreach ($violations as $message) {
                $errors[$field][] = $message;
            }

            if ($context->hasViolations() && $constraint instanceof NotBlank) {
                break;
            }
        }
    }

    /**
     * @return list<ConstraintInterface>
     */
    private function attributeConstraints(ReflectionProperty $prop): array
    {
        $constraints = [];

        foreach ($prop->getAttributes(ConstraintInterface::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            $constraints[] = $attribute->newInstance();
        }

        return $constraints;
    }

    /**
     * @param class-string<ConstraintValidatorInterface> $class
     */
    private function resolveValidator(string $class): ConstraintValidatorInterface
    {
        return $this->validators[$class] ??= $this->container->get($class);
    }

    private function stringifyValue(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE) ?: '[]',
            is_object($value) => $value::class,
            default => (string) $value,
        };
    }

    /**
     * @return list<array{model: class-string, field: string, constraint: class-string, value: string, passed: bool, message: string|null, duration: float}>
     */
    public function getValidationLog(): array
    {
        return $this->log;
    }
}
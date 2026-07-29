<?php

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

                $this->runField($field, $formField->getValue(), $form->getAddedConstraints($field), $model, $form, $errors);
            }
        }

        return $errors;
    }

    /**
     * @param list<ConstraintInterface> $constraints
     * @param array<string, list<string>> $errors
     */
    private function runField(string $field, mixed $value, array $constraints, object $model, ?Form $form, array &$errors): void
    {
        $isEmpty = $value === null || $value === '';

        foreach ($constraints as $constraint) {
            if ($form !== null && $form->isConstraintRemoved($field, $constraint::class)) {
                continue;
            }
            if ($isEmpty && !$constraint->runOnEmpty()) {
                continue;
            }

            $context = new ValidationContext($field, $model, $form);
            $this->resolveValidator($constraint->validatedBy())->validate($value, $constraint, $context);

            foreach ($context->getViolations() as $message) {
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
}
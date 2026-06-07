<?php
declare(strict_types=1);

namespace Neo\Core\Validator;

use Neo\Core\Database\Form\Form;
use Neo\Core\Validator\Assert\EqualToField;
use Neo\Core\Validator\Assert\NotBlank;

class Validator
{
    public function validate(object $model, ?Form $form = null): array
    {
        $errors = [];

        $refClass = new \ReflectionClass($model);

        $handledFields = [];

        foreach ($refClass->getProperties() as $prop) {
            $propertyName = $prop->getName();
            $handledFields[$propertyName] = true;

            $value = $prop->getValue($model);

            $constraints = [];

            $attributes = $prop->getAttributes(Constraint::class, \ReflectionAttribute::IS_INSTANCEOF);
            foreach ($attributes as $attr) {
                $constraints[] = $attr->newInstance();
            }

            if ($form) {
                $constraints = array_merge(
                    $constraints,
                    $form->getAddedConstraints($propertyName)
                );
            }

            $this->runConstraints($constraints, $value, $model, $propertyName, $form, $errors);
        }

        if ($form) {
            foreach ($form->getFields() as $field) {
                $fieldName = $field->getName();

                if (isset($handledFields[$fieldName])) continue;

                $value = $field->getValue();

                $constraints = $form->getAddedConstraints($fieldName);

                $this->runConstraints($constraints, $value, $model, $fieldName, $form, $errors);
            }
        }

        return $errors;
    }

    private function runConstraints(
        array $constraints,
        mixed $value,
        object $model,
        string $fieldName,
        ?Form $form,
        array &$errors
    ): void {

        $isEmpty = $value === null || $value === '';

        foreach ($constraints as $constraint) {
            $constraint->setPropertyName($fieldName);

            $skipIfEmpty = !($constraint instanceof NotBlank || $constraint instanceof EqualToField);
            if ($isEmpty && $skipIfEmpty) {
                continue;
            }

            if ($constraint instanceof EqualToField) {
                $ref = new \ReflectionClass($model);

                if ($ref->hasProperty($constraint->field)) {
                    $otherValue = $ref->getProperty($constraint->field)->getValue($model);

                } elseif ($form && $form->getField($constraint->field) !== null) {
                    $otherValue = $form->getField($constraint->field)->getValue();

                } else {
                    $errors[$fieldName][] = $constraint->message;
                    continue;

                }

                if ($value !== $otherValue) {
                    $errors[$fieldName][] = $constraint->message;
                }

                continue;
            }

            if ($form && $form->isConstraintRemoved($fieldName, get_class($constraint))) {
                continue;
            }

            if (!$constraint->validate($value, $model)) {
                $errors[$fieldName][] = $constraint->message;

                if ($constraint instanceof NotBlank) {
                    break;
                }
            }
        }
    }
}
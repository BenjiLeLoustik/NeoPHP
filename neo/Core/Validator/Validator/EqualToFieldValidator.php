<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Validator;

use Neo\Core\Validator\Assert\EqualToField;
use Neo\Core\Validator\Interface\ConstraintInterface;
use Neo\Core\Validator\Interface\ConstraintValidatorInterface;
use Neo\Core\Validator\ValidationContext;

final class EqualToFieldValidator implements ConstraintValidatorInterface
{
    public function validate(mixed $value, ConstraintInterface $constraint, ValidationContext $context): void
    {
        if (!$constraint instanceof EqualToField) {
            return;
        }

        if (!$context->fieldExists($constraint->field)) {
            $context->addViolation($constraint->getMessage() ?: 'The compared field does not exist.');
            return;
        }

        if ($value !== $context->getValue($constraint->field)) {
            $context->addViolation($constraint->getMessage() ?: 'This value does not match.');
        }
    }
}

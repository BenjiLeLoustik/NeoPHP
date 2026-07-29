<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Validator;

use Neo\Core\Validator\Assert\Range;
use Neo\Core\Validator\Interface\ConstraintInterface;
use Neo\Core\Validator\Interface\ConstraintValidatorInterface;
use Neo\Core\Validator\ValidationContext;

final class RangeValidator implements ConstraintValidatorInterface
{
    public function validate(mixed $value, ConstraintInterface $constraint, ValidationContext $context): void
    {
        if (!$constraint instanceof Range) {
            return;
        }

        if (!is_numeric($value)) {
            $context->addViolation($constraint->message ?: 'This value must be a number.');
            return;
        }

        if ($constraint->min !== null && $value < $constraint->min) {
            $context->addViolation($constraint->message ?: sprintf('This value must be %s or more.', $constraint->min));
            return;
        }

        if ($constraint->max !== null && $value > $constraint->max) {
            $context->addViolation($constraint->message ?: sprintf('This value must be %s or less.', $constraint->max));
        }
    }
}
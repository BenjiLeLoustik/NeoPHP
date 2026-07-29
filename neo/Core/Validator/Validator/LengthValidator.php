<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Validator;

use Neo\Core\Validator\Assert\Length;
use Neo\Core\Validator\Interface\ConstraintInterface;
use Neo\Core\Validator\Interface\ConstraintValidatorInterface;
use Neo\Core\Validator\ValidationContext;

final class LengthValidator implements ConstraintValidatorInterface
{
    public function validate(mixed $value, ConstraintInterface $constraint, ValidationContext $context): void
    {
        if (!$constraint instanceof Length || $value === null) {
            return;
        }

        $len = mb_strlen((string) $value);

        if ($constraint->min !== null && $len < $constraint->min) {
            $context->addViolation($constraint->message ?: sprintf('This value is too short (min %d characters).', $constraint->min));
            return;
        }

        if ($constraint->max !== null && $len > $constraint->max) {
            $context->addViolation($constraint->message ?: sprintf('This value is too long (max %d characters).', $constraint->max));
        }
    }
}
<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Validator;

use Neo\Core\Validator\Assert\Regex;
use Neo\Core\Validator\Interface\ConstraintInterface;
use Neo\Core\Validator\Interface\ConstraintValidatorInterface;
use Neo\Core\Validator\ValidationContext;

final class RegexValidator implements ConstraintValidatorInterface
{
    public function validate(mixed $value, ConstraintInterface $constraint, ValidationContext $context): void
    {
        if (!$constraint instanceof Regex) {
            return;
        }

        if (preg_match($constraint->pattern, (string) $value) !== 1) {
            $context->addViolation($constraint->getMessage() ?: 'This value is not in the expected format.');
        }
    }
}

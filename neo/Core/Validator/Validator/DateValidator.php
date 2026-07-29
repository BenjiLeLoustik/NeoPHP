<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Validator;

use DateTimeImmutable;
use DateTimeInterface;
use Neo\Core\Validator\Assert\Date;
use Neo\Core\Validator\Interface\ConstraintInterface;
use Neo\Core\Validator\Interface\ConstraintValidatorInterface;
use Neo\Core\Validator\ValidationContext;

final class DateValidator implements ConstraintValidatorInterface
{
    public function validate(mixed $value, ConstraintInterface $constraint, ValidationContext $context): void
    {
        if (!$constraint instanceof Date) {
            return;
        }

        $date = $this->normalize($value, $constraint->format);
        if ($date === null) {
            $context->addViolation($constraint->getMessage() ?: 'This value is not a valid date.');
            return;
        }

        if ($constraint->min !== null) {
            $min = $this->normalize($constraint->min, $constraint->format);
            if ($min === null || $date < $min) {
                $context->addViolation($constraint->getMessage() ?: 'This date is too early.');
                return;
            }
        }

        if ($constraint->max !== null) {
            $max = $this->normalize($constraint->max, $constraint->format);
            if ($max === null || $date > $max) {
                $context->addViolation($constraint->getMessage() ?: 'This date is too late.');
            }
        }
    }

    private function normalize(mixed $value, string $format): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (!is_string($value)) {
            return null;
        }

        if (str_starts_with($value, '-') || str_starts_with($value, '+') || $value === 'now') {
            try {
                return new DateTimeImmutable($value);
            } catch (\Exception) {
                return null;
            }
        }

        $date = DateTimeImmutable::createFromFormat($format, $value);
        if ($date === false) {
            return null;
        }

        return $date->format($format) === $value ? $date : null;
    }
}

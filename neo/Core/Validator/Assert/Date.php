<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Assert;

use Neo\Core\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Date extends Constraint
{
    public function __construct(
        public string $format = 'Y-m-d',
        public string|\DateTimeInterface|null $min = null,
        public string|\DateTimeInterface|null $max = null,
        string $message = ''
    ) {
        parent::__construct($message);
    }

    public function validate(mixed $value, ?object $object = null): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $date = $this->normalizeDate($value);
        if (!$date) {
            return false;
        }

        if ($this->min !== null) {
            $minDate = $this->normalizeDate($this->min);
            if (!$minDate || $date < $minDate) {
                return false;
            }
        }

        if ($this->max !== null) {
            $maxDate = $this->normalizeDate($this->max);
            if (!$maxDate || $date > $maxDate) {
                return false;
            }
        }

        return true;
    }

    private function normalizeDate(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        if (!is_string($value)) {
            return null;
        }

        if (str_starts_with($value, '-') || str_starts_with($value, '+') || $value === 'now') {
            try {
                return new \DateTimeImmutable($value);
            } catch (\Exception) {
                return null;
            }
        }

        $d = \DateTimeImmutable::createFromFormat($this->format, $value);
        if ($d === false) {
            return null;
        }

        if ($d->format($this->format) !== $value) {
            return null;
        }

        return $d;
    }
}

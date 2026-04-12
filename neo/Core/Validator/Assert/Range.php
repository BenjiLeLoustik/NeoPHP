<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Assert;

use Neo\Core\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Range extends Constraint
{
    public function __construct(
        public ?float $min = null,
        public ?float $max = null,
        string $message = ""
    ) {
        parent::__construct($message);
    }

    public function validate(mixed $value, ?object $object = null): bool
    {
        if ($value === null) return true;
        if (!is_numeric($value)) return false;

        if ($this->min !== null && $value < $this->min) return false;
        if ($this->max !== null && $value > $this->max) return false;

        return true;
    }
}

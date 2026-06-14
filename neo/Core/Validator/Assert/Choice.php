<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Assert;

use Neo\Core\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Choice extends Constraint
{
    public function __construct(
        /** @var array<int|string, mixed> */
        public array $choices,
        string $message = ""
    ) {
        parent::__construct($message);
    }

    public function validate(mixed $value, ?object $object = null): bool
    {
        if ($value === null) return true;

        if (array_key_exists($value, $this->choices)) return true;

        if (is_numeric($value) && array_key_exists((int)$value, $this->choices)) return false;

        if (in_array($value, $this->choices, false)) return true;

        return false;
    }
}

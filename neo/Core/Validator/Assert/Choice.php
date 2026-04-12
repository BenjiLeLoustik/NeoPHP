<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Assert;

use Neo\Core\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Choice extends Constraint
{
    public function __construct(
        public array $choices,
        string $message = ""
    ) {
        parent::__construct($message);
    }

    public function validate(mixed $value, ?object $object = null): bool
    {
        if ($value === null) return true;
        return in_array($value, $this->choices, true);
    }
}

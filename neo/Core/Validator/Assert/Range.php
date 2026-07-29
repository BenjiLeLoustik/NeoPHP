<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Assert;

use Neo\Core\Validator\Abstract\AbstractConstraint;
use Neo\Core\Validator\Validator\RangeValidator;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Range extends AbstractConstraint
{
    public function __construct(
        public ?float $min = null,
        public ?float $max = null,
        string $message = '',
    ) {
        parent::__construct($message);
    }

    public function validatedBy(): string
    {
        return RangeValidator::class;
    }
}
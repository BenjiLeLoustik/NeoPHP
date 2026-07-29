<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Assert;

use Neo\Core\Validator\Abstract\AbstractConstraint;
use Neo\Core\Validator\Validator\EqualToFieldValidator;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class EqualToField extends AbstractConstraint
{
    public function __construct(
        public string $field,
        string $message = '',
    ) {
        parent::__construct($message);
    }

    public function runOnEmpty(): bool
    {
        return true;
    }

    public function validatedBy(): string
    {
        return EqualToFieldValidator::class;
    }
}
<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Assert;

use Neo\Core\Validator\Abstract\AbstractConstraint;
use Neo\Core\Validator\Validator\NotBlankValidator;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class NotBlank extends AbstractConstraint
{
    public function runOnEmpty(): bool
    {
        return true;
    }

    public function validatedBy(): string
    {
        return NotBlankValidator::class;
    }
}
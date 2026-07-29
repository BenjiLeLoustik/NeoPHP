<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Assert;

use Neo\Core\Validator\Abstract\AbstractConstraint;
use Neo\Core\Validator\Validator\EmailValidator;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Email extends AbstractConstraint
{
    public function validatedBy(): string
    {
        return EmailValidator::class;
    }
}
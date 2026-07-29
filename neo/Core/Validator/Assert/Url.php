<?php
declare(strict_types=1);

namespace Neo\Core\Validator\Assert;

use Neo\Core\Validator\Abstract\AbstractConstraint;
use Neo\Core\Validator\Validator\UrlValidator;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Url extends AbstractConstraint
{
    public function validatedBy(): string
    {
        return UrlValidator::class;
    }
}
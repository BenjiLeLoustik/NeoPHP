<?php

namespace Neo\Core\Validator\Abstract;

use Neo\Core\Validator\Interface\ConstraintInterface;

abstract class AbstractConstraint implements ConstraintInterface
{
    public function __construct(
        public string $message = '',
    ) {
    }

    public function runOnEmpty(): bool
    {
        return false;
    }

    abstract public function validatedBy(): string;
}
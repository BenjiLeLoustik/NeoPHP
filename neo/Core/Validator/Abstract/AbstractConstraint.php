<?php

declare(strict_types=1);

namespace Neo\Core\Validator\Abstract;

use Neo\Core\Validator\Interface\ConstraintInterface;

abstract class AbstractConstraint implements ConstraintInterface
{
    public function __construct(
        public string $message = '',
    ) {
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function runOnEmpty(): bool
    {
        return false;
    }

    abstract public function validatedBy(): string;
}
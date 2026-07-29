<?php

namespace Neo\Core\Validator\Interface;

use Neo\Core\Validator\Interface\ConstraintInterface;
use Neo\Core\Validator\ValidationContext;

interface ConstraintValidatorInterface
{
    public function validate(mixed $value, ConstraintInterface $constraint, ValidationContext $context): void;
}
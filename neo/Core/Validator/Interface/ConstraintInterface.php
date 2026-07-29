<?php

namespace Neo\Core\Validator\Interface;

interface ConstraintInterface
{
    /**
     * @return class-string<ConstraintValidatorInterface>
     */
    public function validatedBy(): string;

    public function runOnEmpty(): bool;
}
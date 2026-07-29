<?php

namespace Neo\Core\Validator\Interface;

interface ConstraintInterface
{
    public function getMessage(): string;

    /**
     * @return class-string<ConstraintValidatorInterface>
     */
    public function validatedBy(): string;

    public function runOnEmpty(): bool;
}
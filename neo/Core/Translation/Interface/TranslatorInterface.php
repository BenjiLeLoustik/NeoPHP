<?php

namespace Neo\Core\Translation\Interface;

interface TranslatorInterface
{
    public function translate(
        string $key,
        ?string $defaultMessage = null,
        array $replace = []
    ): string;
}
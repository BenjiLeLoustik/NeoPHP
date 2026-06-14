<?php

namespace Neo\Core\Translation\Interface;

interface TranslatorInterface
{
    /**
     * @param array<string, mixed> $replace
     */
    public function translate(
        string $key,
        ?string $defaultMessage = null,
        array $replace = []
    ): string;
}
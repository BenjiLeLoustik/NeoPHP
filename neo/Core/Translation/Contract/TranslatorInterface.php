<?php

namespace Neo\Core\Translation\Contract;

interface TranslatorInterface
{
    public function translate(
        string $key,
        ?string $defaultMessage = null,
        array $replace = []
    ): string;
}
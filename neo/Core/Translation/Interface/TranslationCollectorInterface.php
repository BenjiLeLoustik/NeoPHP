<?php

namespace Neo\Core\Translation\Interface;

interface TranslationCollectorInterface
{
    public function record(string $key, string $result, bool $found): void;
}
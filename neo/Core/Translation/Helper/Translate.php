<?php

declare(strict_types=1);

use Neo\Core\DI\Container;
use Neo\Core\DI\ContainerRegistry;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Translation\TranslationManager;

if (!function_exists('translate')) {

    /**
     * @param array<string, mixed> $replace
     * @throws ContainerException
     */
    function translate(string $text, array $replace = [], ?string $domain = null): string
    {
        return ContainerRegistry::get()
            ->get(TranslationManager::class)
            ->translate($text, $replace, $domain);
    }
}
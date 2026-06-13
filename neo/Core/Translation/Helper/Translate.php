<?php

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Translation\TranslationManager;

if (!function_exists('translate')) {
    /**
     * @throws ContainerException
     */
    function translate(string $key, ?string $defaultMessage = null, array $replace = []): string
    {
        return Container::getInstance()
            ->get(TranslationManager::class)
            ->translate($key, $defaultMessage, $replace);
    }
}
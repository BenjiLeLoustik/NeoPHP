<?php

use Neo\Core\DI\ContainerRegistry;
use Neo\Core\Tools\Debug\Dumper;
use Neo\Core\Utils\Config\ConfigManager;

if (!function_exists('dump')) {

    function dump(mixed ...$vars): void
    {
        if (!isDevEnvironment()) {
            return;
        }

        if (PHP_SAPI === 'cli') {
            foreach ($vars as $var) {
                var_dump($var);
            }
            return;
        }

        echo new Dumper()->render($vars);
    }
}

if (!function_exists('dd')) {

    function dd(mixed ...$vars): void
    {
        if (!isDevEnvironment()) {
            return;
        }

        dump(...$vars);
        exit(1);
    }
}

if (!function_exists('isDevEnvironment')) {
    function isDevEnvironment(): bool
    {
        try {
            return ContainerRegistry::get()
                    ->get(ConfigManager::class)
                    ->from('app')
                    ->get('environment') === 'dev';
        } catch (\Throwable) {
            return false;
        }
    }
}
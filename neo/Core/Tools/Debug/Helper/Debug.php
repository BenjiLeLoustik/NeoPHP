<?php

use Neo\Core\DI\ContainerRegistry;
use Neo\Core\Tools\Debug\Dumper;
use Neo\Core\Tools\Debug\DumpRecorder;
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

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
        $caller = isset($trace[0]['file'])
            ? $trace[0]['file'] . ':' . ($trace[0]['line'] ?? '?')
            : null;

        $html = new Dumper()->render($vars);

        if (class_exists(DumpRecorder::class)) {
            DumpRecorder::record($html, $caller);
            return;
        }

        echo $html;
    }
}

if (!function_exists('dd')) {
    function dd(mixed ...$vars): void
    {
        if (!isDevEnvironment()) {
            return;
        }

        if (PHP_SAPI === 'cli') {
            foreach ($vars as $var) {
                var_dump($var);
            }
            exit(1);
        }

        echo new Dumper()->render($vars);
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
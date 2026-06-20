<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Config\Templates;

final class CacheConfigTemplate implements ConfigTemplateInterface
{
    public function filename(): string
    {
        return 'cache.config.php';
    }

    public function render(string $projectName, array $context = []): string
    {
        return <<<PHP
<?php
declare(strict_types=1);

// ./src/$projectName/Config/cache.config.php

return [
    'enabled' => true,
    'driver'  => 'files',
    'ttl'     => 3600,

    'drivers' => [
        'files' => [
            'path' => 'cache',
        ],
        'redis' => [
            'host'     => '127.0.0.1',
            'port'     => 6379,
            'password' => null,
            'database' => 0,
            'prefix'   => '',
        ],
        'array' => [],
    ],
];
PHP;
    }
}
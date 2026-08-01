<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Config\Templates;

use Neo\Core\Utils\Config\Templates\Interface\ConfigTemplateInterface;

final class DatabaseConfigTemplate implements ConfigTemplateInterface
{
    public function filename(): string
    {
        return 'database.config.php';
    }

    public function render(string $projectName, array $context = []): string
    {
        return <<<PHP
<?php
declare(strict_types=1);

// ./src/$projectName/Config/database.config.php

return [
    'enabled' => false,
    'use'     => "default",

    'connections' => [
        'default' => [
            'driver'  => "mysql",
            'host'    => "127.0.0.1",
            'port'    => 3306,
            'user'    => "",
            'pass'    => "",
            'dbname'  => "",
            'charset' => "utf8mb4",
            'prefix'  => "",

            /**
             * Persistent PDO connections (PDO::ATTR_PERSISTENT).
             * Reduces reconnection overhead on high-traffic FPM servers.
             * Warning: an uncommitted/unrolled-back transaction can leak into
             * the next request served by the same worker. Only enable this if
             * the application code guarantees every transaction is closed.
             */
            'persistent' => false,
        ],
    ],
];
PHP;
    }
}
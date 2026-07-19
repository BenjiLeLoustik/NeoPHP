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

// ./src/$projectName/ConfigManager/database.config.php

return [
    'enabled' => false,
    'use'     => "default",

    'connections' => [
        'default' => [
            'driver'  => "mysql",
            'host'    => "localhost",
            'port'    => 3306,
            'user'    => "",
            'pass'    => "",
            'dbname'  => "",
            'charset' => "utf8mb4",
            'prefix'  => "",
        ],
    ],
];
PHP;
    }
}
<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Config\Templates;

use Neo\Core\Utils\Config\Templates\Interface\ConfigTemplateInterface;

final class LoggerConfigTemplate implements ConfigTemplateInterface
{
    public function filename(): string
    {
        return 'logger.config.php';
    }

    public function render(string $projectName, array $context = []): string
    {
        return <<<PHP
<?php
declare(strict_types=1);

// ./src/$projectName/Config/logger.config.php

return [
    'enabled' => false,

    'channels' => [
        'framework' => [
            'enabled'   => false,
            'name'      => 'framework',
            'extension' => 'log',
        ],
        
        // add other channels here
    ],

    'rotation' => [
        'enabled'       => false,
        'type'          => 'daily',
        'max_file_size' => 5 * 1024 * 1024,
    ],

    'archive' => [
        'enabled'   => false,
        'extension' => 'zip',
    ],

    'log_format' => "[{%datetime%}][{%level_name%}][{%level_code%}] [{%origin%}] {%message%} {%context%}",
    'min_level'  => 'DEBUG', // Availables : ['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY']
];
PHP;
    }
}
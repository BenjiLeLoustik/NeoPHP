<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Config\Templates;

use Neo\Core\Utils\Config\Templates\Interface\ConfigTemplateInterface;

final class DeployConfigTemplate implements ConfigTemplateInterface
{
    public function filename(): string
    {
        return 'deploy.config.php';
    }

    public function render(string $projectName, array $context = []): string
    {
        return <<<PHP
<?php
declare(strict_types=1);

// ./src/$projectName/Config/deploy.config.php

return [
    'ftp' => [
        'host' => '',
        'user' => '',
        'pass' => '',
    ],
    'remote' => [
        'domain'        => '',
        'framework_dir' => '',
        'public_dir'    => '',
    ],
];
PHP;
    }
}
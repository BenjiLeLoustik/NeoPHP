<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Config\Templates;

use Neo\Core\Utils\Config\Templates\Interface\ConfigTemplateInterface;

class AuthConfigTemplate implements ConfigTemplateInterface
{

    public function filename(): string
    {
        return 'auth.config.php';
    }

    public function render(string $projectName, array $context = []): string
    {
        return <<<PHP
<?php
declare(strict_types=1);

// ./src/$projectName/ConfigManager/auth.config.php

return [
    'enabled'    => false,
    'model'      => '',
    'identifier' => '',
    'password'   => '',
    'guard'      => 'session',
    'role'       => [
        'model'       => '',
        'foreign_key' => '',
        'field'       => '',
    ],
    'options' => [
        'login'      => '',
        'logout'     => '',
        'home'       => '',
        'secret'     => '',
        'expiration' => 3600,
        'timeout'    => 1800,
        'algorithm'  => 'HS256',
    ],
];
PHP;
    }
}
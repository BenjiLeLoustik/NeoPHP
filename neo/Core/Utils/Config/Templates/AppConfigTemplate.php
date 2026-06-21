<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Config\Templates;

use Neo\Core\Utils\Config\Templates\Interface\ConfigTemplateInterface;

final class AppConfigTemplate implements ConfigTemplateInterface
{
    public function filename(): string
    {
        return 'app.config.php';
    }

    public function render(string $projectName, array $context = []): string
    {
        $host = $context['host'] ?? 'localhost';
        $port = $context['port'] ?? 8000;

        return <<<PHP
<?php
declare(strict_types=1);

// ./src/$projectName/Config/app.config.php

return [
    'general' => [
        'name'        => "$projectName",
        'description' => "Votre projet NeoPHP",
    ],

    'environment' => "dev",

    'access' => "$host:$port",

    'date' => [
        'timezone' => 'Europe/Paris',
    ],

    'translation' => [
        'enabled'           => true,
        'default_locale'    => 'fr',
        'available_locales' => [
            'fr' => 'Français',
            'en' => 'Anglais',
        ],
    ],

    'auth' => [
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
    ],
];
PHP;
    }
}
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
        'name'        => '$projectName',
        'description' => 'Your project description...',
    ],

    'environment' => 'dev', // [dev|prod]

    'access' => '$host:$port',

    'date' => [
        'timezone' => 'Europe/Paris',
    ],

    'translation' => [
        'enabled'           => true,
        'default_locale'    => 'fr',
        'available_locales' => [
            'fr' => 'Français',
            'en' => 'Anglais',
            // Add more here
        ],
    ],
    
    'middlewares' => [
    // \Neo\Src\{$projectName}\App\Middleware\MyMiddleware::class => [
    //     'onError' => 'block',
    //     'redirect' => '',
    //     'message' => '',
    //     'priority' => 1,
    //     'params' => [],
    //     'exclude' => ['route.name', 'xxx.xxx']
    // ]
    ],

    'packages' => [
        // \Vendor\PackageName\PackageName::class,
    ]
];
PHP;
    }
}
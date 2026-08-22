<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Config\Templates;

use Neo\Core\Utils\Config\Templates\Interface\ConfigTemplateInterface;

final class SessionConfigTemplate implements ConfigTemplateInterface
{
    public function filename(): string
    {
        return 'session.config.php';
    }

    public function render(string $projectName, array $context = []): string
    {
        $sessionName = strtoupper($projectName);
        $cookieName  = strtolower($projectName);

        return <<<PHP
<?php
declare(strict_types=1);

// ./src/$projectName/Config/session.config.php

return [
    'session' => [
        'enabled'   => true,
        'name'      => '{$sessionName}_SESSION',
        'lifetime'  => 3600,
        'path'      => '/',
        'domain'    => null,
        'secure'    => false,
        'http_only' => true,
        'same_site' => 'Lax',

        'storage' => [
            'enabled' => true,
            'handler' => 'files',
        ],
    ],

    'cookie' => [
        'prefix'    => '{$cookieName}_',
        'path'      => '/',
        'domain'    => null,
        'secure'    => false,
        'http_only' => true,
        'same_site' => 'Lax',
        'lifetime'  => 86400 * 30,
    ],

    'flash' => [
        'session_key' => '_flash_{$cookieName}',
        'types'       => [
            'success', 
            'error', 
            'warning', 
            'info'
            // Add more types here    
        ],
        'auto_expire' => true,
    ],
];
PHP;
    }
}
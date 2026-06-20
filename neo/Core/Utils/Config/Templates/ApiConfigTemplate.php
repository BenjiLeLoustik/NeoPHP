<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Config\Templates;

final class ApiConfigTemplate implements ConfigTemplateInterface
{
    public function filename(): string
    {
        return 'api.config.php';
    }

    public function render(string $projectName, array $context = []): string
    {
        return <<<PHP
<?php
declare(strict_types=1);

// ./src/$projectName/Config/api.config.php

return [
    'mailer' => [
        'enabled' => false,

        'default' => 'smtp',

        'drivers' => [
            'smtp' => [
                'host'       => '',
                'port'       => 587,
                'encryption' => 'tls',
                'username'   => '',
                'password'   => '',
            ],
        ],

        'from' => [
            'address' => '',
            'name'    => '',
        ],
    ],
    
    'slack' => [
        'enabled'     => false,
        'webhook_url' => '',
        'default'     => [
            'channel'  => '',
            'username' => '',
            'icon'     => '',
        ],
    ],
    
    'sms' => [
        'enabled' => false,

        'default' => 'log',

        'drivers' => [
            'vonage' => [
                'api_key'    => '',
                'api_secret' => '',
                'from'       => '',
            ],
            'twilio' => [
                'account_sid' => '',
                'auth_token'  => '',
                'from'        => '',
            ],
            'log' => [],
        ],
    ],
    
    // Add more API keys
];
PHP;
    }
}
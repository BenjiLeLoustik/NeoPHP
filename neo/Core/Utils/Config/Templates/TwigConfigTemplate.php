<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Config\Templates;

final class TwigConfigTemplate implements ConfigTemplateInterface
{
    public function filename(): string
    {
        return 'twig.config.php';
    }

    public function render(string $projectName, array $context = []): string
    {
        return <<<PHP
<?php
declare(strict_types=1);

// ./src/$projectName/Config/twig.config.php

return [
    'cache'            => true,
    'debug'            => true,
    'auto_reload'      => true,
    'auto_escape'      => 'html',
    'charset'          => 'UTF-8',
    'strict_variables' => false,

    'options' => [
        'optimizations' => -1,
    ],
];
PHP;
    }
}
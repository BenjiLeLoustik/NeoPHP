<?php
declare(strict_types=1);

namespace Neo\Core\Console\Commands;

use Neo\Core\Console\Interface\CommandInterface;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Helper\Args;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Helper\Output;

#[Command(
    name: 'generate:default:config',
    description: 'Generate sensitive config files for a project (deploy, database, api)'
)]
final class GenerateDefaultConfigCommand implements CommandInterface
{
    public function execute(array $args): void
    {
        $projectName = Args::option($args, '--project') ?? $this->pickProjectInteractively();

        if (!$projectName) {
            return;
        }

        $projectPath = ROOT_DIR . "/src/$projectName";

        if (!is_dir($projectPath)) {
            Output::error("Project '$projectName' does not exist inside ./src/");
            return;
        }

        $configPath = "$projectPath/Config/";
        Fs::ensureDir($configPath);

        Output::title("Generating sensitive configs for: $projectName");

        $generated = 0;
        $skipped = 0;

        $files = [
            'database.config.php' => fn() => $this->generateDatabaseConfig($configPath, $projectName),
            'deploy.config.php' => fn() => $this->generateDeployConfig($configPath, $projectName),
            'api.config.php' => fn() => $this->generateAPIConfig($configPath, $projectName),
        ];

        foreach ($files as $filename => $generator) {
            $filePath = $configPath . $filename;

            if (file_exists($filePath)) {
                if (!Output::confirm("  $filename already exists. Overwrite?", 'o')) {
                    Output::skip($filename);
                    $skipped++;
                    continue;
                }
            }

            $generator();
            Output::success("$filename generated.");
            $generated++;
        }

        Output::separator();
        Output::info("Done: $generated file(s) generated, $skipped skipped.");
        Output::newLine();
    }

    private function pickProjectInteractively(): ?string
    {
        $srcDir = ROOT_DIR . '/src/';
        $projects = [];

        foreach (glob($srcDir . '*', GLOB_ONLYDIR) as $dir) {
            $projects[] = basename($dir);
        }

        if (empty($projects)) {
            Output::error('No projects found in ./src/');
            return null;
        }

        Output::title('Available projects:');
        foreach ($projects as $i => $project) {
            echo '  ' . Output::colorize("[$i]", 'cyan') . " $project\n";
        }

        $input = Output::prompt("\nEnter number or project name: ");

        if (is_numeric($input) && isset($projects[(int) $input])) {
            return $projects[(int) $input];
        }

        if (in_array($input, $projects, true)) {
            return $input;
        }

        Output::error("Project not found: '$input'");
        return null;
    }

    private function generateDatabaseConfig(string $path, string $name): void
    {
        $content = <<<PHP
<?php
declare(strict_types=1);

// ./src/$name/Config/database.config.php

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
        file_put_contents($path . 'database.config.php', $content);
    }

    private function generateDeployConfig(string $path, string $name): void
    {
        $content = <<<PHP
<?php
declare(strict_types=1);

// ./src/$name/Config/deploy.config.php

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
        file_put_contents($path . 'deploy.config.php', $content);
    }

    private function generateAPIConfig(string $path, string $name): void
    {
        $content = <<<PHP
<?php
declare(strict_types=1);

// ./src/$name/Config/api.config.php

return [
    // 'stripe' => [
    //     'key'    => '',
    //     'secret' => '',
    // ],
];
PHP;
        file_put_contents($path . 'api.config.php', $content);
    }

    public function getName(): string
    {
        return 'generate:default:config';
    }

    public function getDescription(): string
    {
        return 'Generate sensitive config files for a project (deploy, database, api)';
    }

    public function getHelp(): string
    {
        Output::usage('generate:default:config', $this->getDescription());
        Output::option('--project=<name>', 'Target project inside ./src/ (interactive if omitted)');
        Output::newLine();
        echo "  Generated files:\n";
        Output::muted('    Config/deploy.config.php');
        Output::muted('    Config/database.config.php');
        Output::muted('    Config/api.config.php');
        Output::newLine();
        echo "  Examples:\n";
        Output::example('php bin/neo generate:default:config');
        Output::example('php bin/neo generate:default:config --project=NeoAdmin');

        return '';
    }
}
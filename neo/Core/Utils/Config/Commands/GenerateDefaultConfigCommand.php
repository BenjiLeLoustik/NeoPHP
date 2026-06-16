<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Config\Commands;

use Neo\Core\Console\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Output\Output;

#[Command(
    name: 'generate:default:config',
    description: 'Generate sensitive config files for a project',
    category: 'Config',
)]
final class GenerateDefaultConfigCommand extends AbstractCommand
{
    public function configure(): void
    {
        $this->addOption(
            name: 'project',
            shortcut: null,
            mode: InputOption::REQUIRED,
            description: 'Target project',
        );
    }

    public function do(Input $input, Output $output): ExitCode
    {
        $project = $input->getOption('project') ?? Input::choice('Target project ?', $this->getAvailableProjects());
        $projectPath = ROOT_DIR . "/src/$project";

        if (!is_dir($projectPath)) {
            Output::error("Project '$project' not found.");
            return ExitCode::FAILURE;
        }

        $configPath = "$projectPath/Config/";
        Fs::ensureDir($configPath);

        Output::title("Generating configs for: $project");

        $files = [
            'database.config.php' => fn() => $this->generateDatabaseConfig($configPath, $project),
            'deploy.config.php' => fn() => $this->generateDeployConfig($configPath, $project),
            'api.config.php' => fn() => $this->generateAPIConfig($configPath, $project),
            'mailer.config.php' => fn() => $this->generateMailerConfig($configPath, $project),
        ];

        foreach ($files as $filename => $generator) {
            if (file_exists($configPath . $filename) && !Input::confirm("$filename exists. Overwrite ?", false)) {
                Output::skip($filename);
                continue;
            }
            $generator();
            Output::success("$filename generated.");
        }

        return ExitCode::SUCCESS;
    }

    private function generateDatabaseConfig(string $path, string $name): void
    {
        file_put_contents($path . 'database.config.php', "<?php\ndeclare(strict_types=1);\n\nreturn [\n    'enabled' => false,\n    'connections' => [\n        'default' => ['driver' => 'mysql', 'host' => 'localhost', 'port' => 3306, 'user' => '', 'pass' => '', 'dbname' => '', 'charset' => 'utf8mb4', 'prefix' => ''],\n    ],\n];");
    }

    private function generateDeployConfig(string $path, string $name): void
    {
        file_put_contents($path . 'deploy.config.php', "<?php\ndeclare(strict_types=1);\n\nreturn [\n    'ftp' => ['host' => '', 'user' => '', 'pass' => ''],\n    'remote' => ['domain' => '', 'framework_dir' => '', 'public_dir' => ''],\n];");
    }

    private function generateAPIConfig(string $path, string $name): void
    {
        file_put_contents($path . 'api.config.php', "<?php\ndeclare(strict_types=1);\n\nreturn [\n    // API configurations\n];");
    }

    private function generateMailerConfig(string $path, string $name): void
    {
        file_put_contents($path . 'mailer.config.php', "<?php\ndeclare(strict_types=1);\n\nreturn [\n    'enabled' => false,\n    'default' => 'smtp',\n    'drivers' => [\n        'smtp' => ['host' => '', 'port' => 587, 'encryption' => 'tls', 'username' => '', 'password' => ''],\n    ],\n    'from' => ['address' => '', 'name' => '$name'],\n];");
    }

    protected function getAvailableProjects(): array
    {
        return array_map('basename', glob(ROOT_DIR . '/src/*', GLOB_ONLYDIR));
    }
}
<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Config\Commands;

use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Helper\Args;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Helper\Input;
use Neo\Core\Console\Helper\Output;
use Neo\Core\Console\Interface\CommandInterface;

#[Command(
    name: 'make:config',
    description: 'Create an interactive config file for a project'
)]
final class MakeConfigCommand implements CommandInterface
{
    public function execute(array $args): void
    {
        $configName = Args::positional($args, 0);
        $project = Args::option($args, '--project');
        $force = Args::flag($args, '--force');

        if (!$configName) {
            $configName = Input::ask('Config file name ?');
            if (!$configName) {
                Output::error('Config name is required.');
                return;
            }
        }

        if (!$project) {
            $projects = $this->getAvailableProjects();

            if (empty($projects)) {
                Output::error('No projects found in ./src/');
                return;
            }

            $project = Input::choice('Target project ?', $projects);
        }

        $configName = strtolower($configName);
        $basePath = ROOT_DIR . "/src/$project";

        if (!is_dir($basePath)) {
            Output::error("Project '$project' does not exist inside ./src/");
            return;
        }

        $configDir = "$basePath/Config";
        $configFile = "$configDir/$configName.config.php";

        if (file_exists($configFile) && !$force) {
            if (!Input::confirm("'$configName.config.php' already exists. Overwrite ?", false)) {
                Output::muted('Cancelled.');
                return;
            }
        }

        Output::info("Generating '$configName.config.php' for project '$project'.");
        Output::muted('  (Dot notation supported: ftp.host, ftp.user, remote.domain…)');
        Output::newLine();

        $entries = $this->collectEntries();

        Fs::ensureDir($configDir);

        $content = $this->buildFileContent($configName, $project, $entries);
        file_put_contents($configFile, $content);

        Output::success("'$configName.config.php' generated successfully.");
    }

    private function collectEntries(): array
    {
        $flat = [];

        while (true) {
            $key = Input::ask('Key name (empty to finish)', '');

            if ($key === '') {
                break;
            }

            $value = Input::ask("Value for '$key'", '');
            $flat[$key] = $value;
            Output::newLine();
        }

        return $this->expandDotKeys($flat);
    }

    private function expandDotKeys(array $flat): array
    {
        $result = [];

        foreach ($flat as $key => $value) {
            $parts = explode('.', $key);
            $current = &$result;

            foreach ($parts as $part) {
                if (!isset($current[$part]) || !is_array($current[$part])) {
                    $current[$part] = [];
                }
                $current = &$current[$part];
            }

            $current = $value;
        }

        return $result;
    }

    private function buildFileContent(string $configName, string $project, array $entries): string
    {
        $filePath = "./src/$project/Config/$configName.config.php";
        $body = $this->buildArray($entries, 1);

        return <<<PHP
<?php
declare(strict_types=1);

// $filePath

return $body;
PHP;
    }

    private function buildArray(array $entries, int $depth): string
    {
        if (empty($entries)) {
            return '[]';
        }

        $indent = str_repeat('    ', $depth);
        $indentClose = str_repeat('    ', $depth - 1);
        $lines = [];

        foreach ($entries as $key => $value) {
            $formattedKey = "'" . addslashes((string) $key) . "'";
            $formattedValue = is_array($value)
                ? $this->buildArray($value, $depth + 1)
                : $this->formatValue((string) $value);
            $lines[] = "{$indent}{$formattedKey} => {$formattedValue},";
        }

        return "[\n" . implode("\n", $lines) . "\n{$indentClose}]";
    }

    private function formatValue(string $value): string
    {
        if ($value === '') {
            return "''";
        }

        if (in_array(strtolower($value), ['true', 'false'], true)) {
            return strtolower($value);
        }

        if (is_numeric($value)) {
            return $value;
        }

        return "'" . addslashes($value) . "'";
    }

    private function getAvailableProjects(): array
    {
        $srcDir = ROOT_DIR . '/src/';

        if (!is_dir($srcDir)) {
            return [];
        }

        return array_map(
            fn(string $dir) => basename($dir),
            glob($srcDir . '*', GLOB_ONLYDIR) ?: []
        );
    }

    public function getName(): string
    {
        return 'make:config';
    }

    public function getDescription(): string
    {
        return 'Create an interactive config file for a project';
    }

    public function getHelp(): string
    {
        Output::usage('make:config', $this->getDescription());
        Output::option('<ConfigName>', 'Config file name (e.g. mail → mail.config.php)');
        Output::option('--project=<name>', 'Target project inside ./src/ (interactive selection if omitted)');
        Output::option('--force', 'Overwrite existing file');
        Output::newLine();
        echo "  Examples:\n";
        Output::example('php bin/neo make:config mail --project=NeoAdmin');
        Output::example('php bin/neo make:config');

        return '';
    }
}
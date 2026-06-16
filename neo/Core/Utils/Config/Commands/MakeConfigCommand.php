<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Config\Commands;

use Neo\Core\Console\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputArgument;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Output\Output;

#[Command(
    name: 'make:config',
    description: 'Create an interactive config file for a project',
    category: 'Config',
)]
final class MakeConfigCommand extends AbstractCommand
{
    public function configure(): void
    {
        $this->addArgument(
            name: 'configName',
            description: 'Config file name',
            mode: InputArgument::OPTIONAL,
        );

        $this->addOption(
            name: 'project',
            shortcut: null,
            mode: InputOption::REQUIRED,
            description: 'Target project',
        );

        $this->addOption(
            name: 'force',
            shortcut: null,
            mode: InputOption::NONE,
            description: 'Overwrite file',
        );
    }

    public function do(Input $input, Output $output): ExitCode
    {
        $configName = strtolower($input->getArgument('configName') ?? Input::ask('Config file name ?'));
        if (!$configName) return ExitCode::INVALID;

        $project = $input->getOption('project') ?? Input::choice('Target project ?', $this->getAvailableProjects());
        $force = (bool) $input->getOption('force');

        $basePath = ROOT_DIR . "/src/$project";
        if (!is_dir($basePath)) {
            Output::error("Project '$project' not found.");
            return ExitCode::FAILURE;
        }

        $configDir = "$basePath/Config";
        $configFile = "$configDir/$configName.config.php";

        if (file_exists($configFile) && !$force) {
            if (!Input::confirm("Config '$configName' exists. Overwrite ?", false)) {
                Output::muted('Cancelled.');
                return ExitCode::SUCCESS;
            }
        }

        Output::info("Generating '$configName.config.php' for '$project'.");
        $entries = $this->collectEntries();

        Fs::ensureDir($configDir);
        file_put_contents($configFile, $this->buildFileContent($configName, $project, $entries));

        Output::success("Config generated.");
        return ExitCode::SUCCESS;
    }

    private function collectEntries(): array
    {
        $flat = [];
        while (true) {
            $key = Input::ask('Key name (empty to finish)', '');
            if ($key === '') break;
            $flat[$key] = Input::ask("Value for '$key'", '');
        }
        return $this->expandDotKeys($flat);
    }

    private function expandDotKeys(array $flat): array
    {
        $result = [];
        foreach ($flat as $key => $value) {
            $parts = explode('.', (string)$key);
            $current = &$result;
            foreach ($parts as $part) {
                if (!isset($current[$part]) || !is_array($current[$part])) $current[$part] = [];
                $current = &$current[$part];
            }
            $current = $value;
        }
        return $result;
    }

    private function buildFileContent(string $name, string $proj, array $entries): string
    {
        $body = $this->buildArray($entries, 1);
        return "<?php\ndeclare(strict_types=1);\n\n// ./src/$proj/Config/$name.config.php\n\nreturn $body;";
    }

    private function buildArray(array $entries, int $depth): string
    {
        $indent = str_repeat('    ', $depth);
        $indentClose = str_repeat('    ', $depth - 1);
        $lines = [];

        foreach ($entries as $key => $value) {
            $val = is_array($value) ? $this->buildArray($value, $depth + 1) : $this->formatValue((string)$value);
            $lines[] = "{$indent}'" . addslashes((string)$key) . "' => {$val},";
        }
        return "[\n" . implode("\n", $lines) . "\n{$indentClose}]";
    }

    private function formatValue(string $v): string
    {
        if ($v === '') return "''";
        if (in_array(strtolower($v), ['true', 'false'], true)) return strtolower($v);
        return is_numeric($v) ? $v : "'" . addslashes($v) . "'";
    }

    protected function getAvailableProjects(): array
    {
        return array_map('basename', glob(ROOT_DIR . '/src/*', GLOB_ONLYDIR));
    }
}
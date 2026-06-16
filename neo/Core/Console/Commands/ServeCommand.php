<?php
declare(strict_types=1);

namespace Neo\Core\Console\Commands;

use Neo\Core\Console\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputArgument;
use Neo\Core\Console\Output\Output;

#[Command(
    name: 'app:serve',
    description: 'Start the PHP built-in server for a NeoPHP project',
    category: 'Server',
)]
final class ServeCommand extends AbstractCommand
{

    public function configure(): void
    {
        $this->addArgument(
            'project',
            'Project to serve (Interactive selection if omitted)',
            InputArgument::OPTIONAL
        );
    }

    public function do(Input $input, Output $output): ExitCode
    {
        $projectArg = $input->getArgument('project');
        $projects = $this->getProjects();

        if (empty($projects)) {
            Output::error('No projects found in ./src/');
            return ExitCode::FAILURE;
        }

        if ($projectArg) {
            return $this->runProject($projectArg, $projects);
        }

        Output::title('Available projects:');

        $i = 1;
        $keys = [];

        foreach ($projects as $name => $config) {
            $access = $config['access'] ?? '?';
            echo '  ' . Output::colorize("[$i]", 'cyan') . " $name " . Output::colorize("→ http://$access", 'dim') . "\n";
            $keys[$i] = $name;
            $i++;
        }

        $selected = Input::choice('Choose a project', array_keys($projects));

        return $this->runProject($selected, $projects);
    }

    /**
     * @param array<string, array<string, mixed>> $projects
     */
    private function runProject(string $project, array $projects): ExitCode
    {
        if (!isset($projects[$project])) {
            Output::error("Project not found: $project");
            return ExitCode::FAILURE;
        }

        $config = $projects[$project];

        if (!isset($config['access'])) {
            Output::error("Key 'access' missing in app.config.php");
            return ExitCode::FAILURE;
        }

        $access = $config['access'];

        Output::newLine();
        Output::success("Starting server for $project");
        Output::label('URL:', "http://$access");
        Output::newLine();

        passthru("php -S $access -t public");

        return ExitCode::SUCCESS;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getProjects(): array
    {
        $src = ROOT_DIR . 'src/';
        $dirs = glob($src . '*', GLOB_ONLYDIR);

        $projects = [];

        foreach ($dirs as $dir) {
            $name = basename($dir);
            $configPath = $dir . '/Config/app.config.php';

            if (!file_exists($configPath)) {
                continue;
            }

            $config = include $configPath;

            if (!is_array($config)) {
                continue;
            }

            $projects[$name] = $config;
        }

        return $projects;
    }
}
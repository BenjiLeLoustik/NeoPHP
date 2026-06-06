<?php
declare(strict_types=1);

namespace Neo\Core\Console\Commands;

use Neo\Core\Console\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Helper\Args;
use Neo\Core\Console\Helper\Input;
use Neo\Core\Console\Helper\Output;

#[Command(
    name: 'app:serve',
    description: 'Start the PHP built-in server for a NeoPHP project',
    category: 'Server'
)]
final class ServeCommand extends AbstractCommand
{
    public function execute(array $args): void
    {
        $projectArg = Args::positional($args, 0);
        $projects = $this->getProjects();

        if (empty($projects)) {
            Output::error('No projects found in ./src/');
            return;
        }

        if ($projectArg) {
            $this->runProject($projectArg, $projects);
            return;
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
        $this->runProject($selected, $projects);
    }

    private function runProject(string $project, array $projects): void
    {
        if (!isset($projects[$project])) {
            Output::error("Project not found: $project");
            return;
        }

        $config = $projects[$project];

        if (!isset($config['access'])) {
            Output::error("Key 'access' missing in app.config.php");
            return;
        }

        $access = $config['access'];

        Output::newLine();
        Output::success("Starting server for $project");
        Output::label('URL:', "http://$access");
        Output::newLine();

        passthru("php -S $access -t public");
    }

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

    public function getHelp(): string
    {
        Output::usage($this->getName(), $this->getDescription());
        Output::option('<ProjectName>', 'Project to serve (interactive selection if omitted)');
        Output::newLine();
        echo "  Prerequisites:\n";
        Output::muted("    Each project must have src/<Project>/Config/app.config.php");
        Output::muted("    with an 'access' key (e.g. '127.0.0.1:8000').");
        Output::newLine();
        echo "  Examples:\n";
        Output::example("php bin/neo {$this->getName()}");
        Output::example("php bin/neo {$this->getName()} MyApp");

        return '';
    }
}
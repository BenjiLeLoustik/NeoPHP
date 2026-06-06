<?php
declare(strict_types=1);

namespace Neo\Core\Event\Commands;

use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Helper\Args;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Helper\Input;
use Neo\Core\Console\Helper\Output;
use Neo\Core\Console\Interface\CommandInterface;

#[Command(
    name: 'make:event',
    description: 'Create an Event class for a project',
    category: 'Event'
)]
final class MakeEventCommand implements CommandInterface
{
    public function execute(array $args): void
    {
        $event = Args::positional($args, 0);
        $project = Args::option($args, '--project');
        $force = Args::flag($args, '--force');

        if (!$event) {
            $event = Input::ask('Event name ?');
            if (!$event) {
                Output::error('Event name is required.');
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

        $event = $this->normalizeEventName($event);
        $basePath = ROOT_DIR . "/src/$project/App/Event";

        Fs::ensureDir($basePath);

        $path = "$basePath/$event.php";

        if (file_exists($path) && !$force) {
            if (!Input::confirm("Event '$event' already exists. Overwrite ?", false)) {
                Output::muted('Cancelled.');
                return;
            }
        }

        $namespace = "Neo\\Src\\$project\\App\\Event";

        $content = <<<PHP
<?php
declare(strict_types=1);

namespace $namespace;

use Neo\Core\Event\AbstractEvent;

final class $event extends AbstractEvent
{
    public function __construct(
        // TODO: add event data properties
    ) {}
}
PHP;

        file_put_contents($path, $content);
        Output::success("Event '$event' generated for project '$project'.");
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

    private function normalizeEventName(string $input): string
    {
        $input = preg_replace('/[^a-zA-Z0-9]+/', ' ', $input);
        $input = str_replace(' ', '', ucwords($input));

        if (!str_ends_with($input, 'Event')) {
            $input .= 'Event';
        }

        return $input;
    }

    public function getName(): string
    {
        return 'make:event';
    }

    public function getDescription(): string
    {
        return 'Create an Event class for a project';
    }

    public function getHelp(): string
    {
        Output::usage('make:event', $this->getDescription());
        Output::option('<EventName>', '"Event" suffix added automatically');
        Output::option('--project=<name>', 'Target project inside ./src/ (interactive selection if omitted)');
        Output::option('--force', 'Overwrite existing file');
        Output::newLine();
        echo "  Examples:\n";
        Output::example('php bin/neo make:event UserRegistered --project=NeoAdmin');
        Output::example('php bin/neo make:event');

        return '';
    }
}
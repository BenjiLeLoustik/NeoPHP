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
    name: 'make:event:listener',
    description: 'Create a Listener for an Event in a project',
    category: 'Event'
)]
final class MakeEventListenerCommand implements CommandInterface
{
    public function execute(array $args): void
    {
        $listener = Args::positional($args, 0);
        $project = Args::option($args, '--project');
        $event = Args::option($args, '--event');
        $priority = (int) (Args::option($args, '--priority') ?? 0);
        $force = Args::flag($args, '--force');

        if (!$listener) {
            $listener = Input::ask('Listener name ?');
            if (!$listener) {
                Output::error('Listener name is required.');
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

        if (!$event) {
            $available = $this->getAvailableEvents($project);

            if (!empty($available)) {
                $event = Input::choice('Event to listen to ?', $available);
            } else {
                $event = Input::ask('Event to listen to ?');
                if (!$event) {
                    Output::error('Event name is required.');
                    return;
                }
            }
        }

        if ($priority === 0) {
            $raw = Input::ask('Listener priority ?', '0');
            $priority = (int) $raw;
        }

        $listener = $this->normalizeListenerName($listener);
        $event = $this->normalizeEventName($event);
        $basePath = ROOT_DIR . "/src/$project/App/Event/Listener";

        Fs::ensureDir($basePath);

        $path = "$basePath/$listener.php";

        if (file_exists($path) && !$force) {
            if (!Input::confirm("Listener '$listener' already exists. Overwrite ?", false)) {
                Output::muted('Cancelled.');
                return;
            }
        }

        $listenerNs = "Neo\\Src\\$project\\App\\Event\\Listener";
        $eventNs = "Neo\\Src\\$project\\App\\Event";
        $eventFqcn  = "$eventNs\\$event";

        $content = <<<PHP
<?php
declare(strict_types=1);

namespace $listenerNs;

use Neo\Core\DI\Container;
use Neo\Core\Event\Attribute\AsListener;
use $eventFqcn;

#[AsListener(event: $event::class, priority: $priority)]
final class $listener
{
    public function __construct(private Container \$container) {}

    public function handle($event \$event): void
    {
        // TODO: implement listener logic
    }
}
PHP;

        file_put_contents($path, $content);
        Output::success("Listener '$listener' generated for event '$event' in project '$project'.");
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

    private function getAvailableEvents(string $project): array
    {
        $eventDir = ROOT_DIR . "/src/$project/App/Event";

        if (!is_dir($eventDir)) {
            return [];
        }

        $files = glob($eventDir . '/*Event.php') ?: [];

        return array_map(
            fn(string $file) => basename($file, '.php'),
            $files
        );
    }

    private function normalizeListenerName(string $input): string
    {
        $input = preg_replace('/[^a-zA-Z0-9]+/', ' ', $input);
        $input = str_replace(' ', '', ucwords($input));

        if (!str_ends_with($input, 'Listener')) {
            $input .= 'Listener';
        }

        return $input;
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
        return 'make:event:listener';
    }

    public function getDescription(): string
    {
        return 'Create a Listener for an Event in a project';
    }

    public function getHelp(): string
    {
        Output::usage('make:event:listener', $this->getDescription());
        Output::option('<ListenerName>', '"Listener" suffix added automatically');
        Output::option('--event=<name>', 'Event to listen to — interactive selection from existing events if omitted');
        Output::option('--priority=<n>', 'Listener priority (default: 0, higher = earlier)');
        Output::option('--project=<name>', 'Target project inside ./src/ (interactive selection if omitted)');
        Output::option('--force', 'Overwrite existing file');
        Output::newLine();
        echo "  Examples:\n";
        Output::example('php bin/neo make:event:listener SendWelcomeEmail --event=UserRegistered --project=NeoAdmin');
        Output::example('php bin/neo make:event:listener SendWelcomeEmail --event=UserRegistered --priority=10 --project=NeoAdmin');
        Output::example('php bin/neo make:event:listener');

        return '';
    }
}
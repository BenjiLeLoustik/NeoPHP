<?php
declare(strict_types=1);

namespace Neo\Core\Console\Commands;

use Neo\Core\Console\Interface\CommandInterface;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Helper\Args;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Helper\Output;

#[Command(
    name: 'make:event:listener',
    description: 'Create a Listener for an Event in a project'
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

        if (!$listener || !$project || !$event) {
            Output::error('Missing arguments.');
            Output::muted('Usage: php bin/neo make:event:listener <ListenerName> --event=<EventName> --project=<name>');
            return;
        }

        $listener = $this->normalizeListenerName($listener);
        $event = $this->normalizeEventName($event);
        $basePath = ROOT_DIR . "/src/$project/App/Event/Listener";

        Fs::ensureDir($basePath);

        $path = "$basePath/$listener.php";

        if (file_exists($path) && !$force) {
            Output::warning("Listener already exists. Use --force to overwrite.");
            return;
        }

        $listenerNs = "Neo\\Src\\$project\\App\\Event\\Listener";
        $eventNs = "Neo\\Src\\$project\\App\\Event";
        $eventFqcn = "$eventNs\\$event";

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
        Output::option('<ListenerName>',    '"Listener" suffix added automatically');
        Output::option('--event=<name>',    'Event to listen to (e.g. UserRegistered)');
        Output::option('--priority=<n>',    'Listener priority (default: 0, higher = earlier)');
        Output::option('--project=<name>',  'Target project inside ./src/');
        Output::option('--force',           'Overwrite existing file');
        Output::newLine();
        echo "  Examples:\n";
        Output::example('php bin/neo make:event:listener SendWelcomeEmail --event=UserRegistered --project=NeoAdmin');
        Output::example('php bin/neo make:event:listener SendWelcomeEmail --event=UserRegistered --priority=10 --project=NeoAdmin');

        return '';
    }
}
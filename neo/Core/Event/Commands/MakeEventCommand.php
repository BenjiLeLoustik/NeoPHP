<?php
declare(strict_types=1);

namespace Neo\Core\Event\Commands;

use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Helper\Args;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Helper\Output;
use Neo\Core\Console\Interface\CommandInterface;

#[Command(
    name: 'make:event',
    description: 'Create an Event class for a project'
)]
final class MakeEventCommand implements CommandInterface
{
    public function execute(array $args): void
    {
        $event = Args::positional($args, 0);
        $project = Args::option($args, '--project');
        $force = Args::flag($args, '--force');

        if (!$event || !$project) {
            Output::error('Missing arguments.');
            Output::muted('Usage: php bin/neo make:event <EventName> --project=<name>');
            return;
        }

        $event = $this->normalizeEventName($event);
        $basePath = ROOT_DIR . "/src/$project/App/Event";

        Fs::ensureDir($basePath);

        $path = "$basePath/$event.php";

        if (file_exists($path) && !$force) {
            Output::warning("Event already exists. Use --force to overwrite.");
            return;
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
        Output::option('<EventName>',      'Event class name — "Event" suffix added automatically');
        Output::option('--project=<name>', 'Target project inside ./src/');
        Output::option('--force',          'Overwrite existing file');
        Output::newLine();
        echo "  Examples:\n";
        Output::example('php bin/neo make:event UserRegistered --project=NeoAdmin');

        return '';
    }
}
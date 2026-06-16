<?php
declare(strict_types=1);

namespace Neo\Core\Event\Commands;

use Neo\Core\Console\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputArgument;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Output\Output;

#[Command(
    name: 'make:event:listener',
    description: 'Create a Listener for an Event in a project',
    category: 'Event',
)]
final class MakeEventListenerCommand extends AbstractCommand
{
    public function configure(): void
    {
        $this->addArgument(
            name: 'listener',
            description: 'Listener class name',
            mode: InputArgument::OPTIONAL,
        );

        $this->addOption(
            name: 'project',
            shortcut: null,
            mode: InputOption::REQUIRED,
            description: 'Target project',
        );

        $this->addOption(
            name: 'event',
            shortcut: null,
            mode: InputOption::REQUIRED,
            description: 'Event to listen to',
        );

        $this->addOption(
            name: 'priority',
            shortcut: null,
            mode: InputOption::REQUIRED,
            description: 'Listener priority',
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
        $listener = $input->getArgument('listener') ?? Input::ask('Listener name ?');
        if (!$listener) return ExitCode::INVALID;

        $project = $input->getOption('project') ?? Input::choice('Target project ?', $this->getAvailableProjects());
        $event = $input->getOption('event') ?? $this->resolveEvent($project);
        $priority = (int) ($input->getOption('priority') ?? Input::ask('Priority ?', '0'));
        $force = (bool) $input->getOption('force');

        if (!$event) {
            Output::error('Event name is required.');
            return ExitCode::INVALID;
        }

        $listener = $this->normalizeListenerName($listener);
        $event = $this->normalizeEventName($event);
        $basePath = ROOT_DIR . "/src/$project/App/Event/Listener";

        Fs::ensureDir($basePath);
        $path = "$basePath/$listener.php";

        if (file_exists($path) && !$force) {
            if (!Input::confirm("Listener '$listener' exists. Overwrite ?", false)) {
                Output::muted('Cancelled.');
                return ExitCode::SUCCESS;
            }
        }

        $listenerNs = "Neo\\Src\\$project\\App\\Event\\Listener";
        $eventFqcn = "Neo\\Src\\$project\\App\\Event\\$event";

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
        Output::success("Listener '$listener' generated.");

        return ExitCode::SUCCESS;
    }

    private function resolveEvent(string $project): ?string
    {
        $eventDir = ROOT_DIR . "/src/$project/App/Event";
        if (!is_dir($eventDir)) return Input::ask('Event name ?');

        $files = glob($eventDir . '/*Event.php') ?: [];
        $choices = array_map(fn($f) => basename($f, '.php'), $files);

        return !empty($choices) ? Input::choice('Event to listen to ?', $choices) : Input::ask('Event name ?');
    }

    private function normalizeListenerName(string $input): string
    {
        $input = str_replace(' ', '', ucwords(preg_replace('/[^a-zA-Z0-9]+/', ' ', $input)));
        return str_ends_with($input, 'Listener') ? $input : $input . 'Listener';
    }

    private function normalizeEventName(string $input): string
    {
        $input = str_replace(' ', '', ucwords(preg_replace('/[^a-zA-Z0-9]+/', ' ', $input)));
        return str_ends_with($input, 'Event') ? $input : $input . 'Event';
    }

    protected function getAvailableProjects(): array
    {
        return array_map('basename', glob(ROOT_DIR . '/src/*', GLOB_ONLYDIR));
    }
}
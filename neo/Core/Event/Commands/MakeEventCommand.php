<?php
declare(strict_types=1);

namespace Neo\Core\Event\Commands;

use Neo\Core\Console\Abstract\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputArgument;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Output\Output;

#[Command(
    name: 'make:event',
    description: 'Create an Event class for a project',
    category: 'Event',
)]
final class MakeEventCommand extends AbstractCommand
{
    public function configure(): void
    {
        $this->addArgument(
            name: 'event',
            description: 'Event class name',
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
        $event = $input->getArgument('event') ?? Input::ask('Event name ?');
        if (!$event) return ExitCode::INVALID;

        $project = $input->getOption('project') ?? Input::choice('Target project ?', $this->getAvailableProjects());
        $force = (bool) $input->getOption('force');

        $event = $this->normalizeEventName($event);
        $basePath = ROOT_DIR . "/src/$project/App/Event";

        Fs::ensureDir($basePath);
        $path = "$basePath/$event.php";

        if (file_exists($path) && !$force) {
            if (!Input::confirm("Event '$event' exists. Overwrite ?", false)) {
                Output::muted('Cancelled.');
                return ExitCode::SUCCESS;
            }
        }

        $namespace = "Neo\\Src\\$project\\App\\Event";
        $content = <<<PHP
<?php
declare(strict_types=1);

namespace $namespace;

final class $event extends AbstractEvent
{
    public function __construct(
        // TODO: add event data properties
    ) {}
}
PHP;

        file_put_contents($path, $content);
        Output::success("Event '$event' generated.");

        return ExitCode::SUCCESS;
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
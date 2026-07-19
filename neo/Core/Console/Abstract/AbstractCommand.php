<?php
declare(strict_types=1);

namespace Neo\Core\Console\Abstract;

use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputArgument;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Interface\CommandInterface;
use Neo\Core\Console\Output\Output;
use Neo\Core\Utils\Scanner\ScannerAttributeManager;

abstract class AbstractCommand implements CommandInterface
{
    /** @var list<InputArgument> */
    private array $argumentDefinitions = [];

    /** @var list<InputOption> */
    private array $optionDefinitions = [];

    final protected function addArgument(
        string $name,
        string $description = '',
        int $mode = InputArgument::OPTIONAL,
        mixed $default = null,
    ): static {
        $this->argumentDefinitions[] = new InputArgument($name, $description, $mode, $default);

        return $this;
    }

    final protected function addOption(
        string $name,
        ?string $shortcut = null,
        int $mode = InputOption::NONE,
        string $description = '',
        mixed $default = null,
    ): static {
        $this->optionDefinitions[] = new InputOption($name, $shortcut, $mode, $description, $default);

        return $this;
    }

    /** @return list<InputArgument> */
    final public function getArgumentDefinitions(): array
    {
        return $this->argumentDefinitions;
    }

    /** @return list<InputOption> */
    final public function getOptionDefinitions(): array
    {
        return $this->optionDefinitions;
    }

    public function configure(): void {}

    abstract public function do(Input $input, Output $output): ExitCode;

    final public function renderHelp(): void
    {
        Output::usage($this->getName(), $this->getDescription());

        if ($this->argumentDefinitions !== []) {
            echo Output::colorize("  Arguments:\n", 'bold');

            foreach ($this->argumentDefinitions as $arg) {
                Output::argument(
                    '<' . $arg->getName() . '>',
                    $arg->getDescription(),
                    $arg->getModeLabel(),
                );
            }

            Output::newLine();
        }

        if ($this->optionDefinitions !== []) {
            echo Output::colorize("  Options:\n", 'bold');

            foreach ($this->optionDefinitions as $opt) {
                Output::option($opt->getSynopsis(), $opt->getDescription());
            }

            Output::newLine();
        }

        echo Output::colorize("  Global options:\n", 'bold');
        Output::option('--help, -h', 'Show this help message');
        Output::newLine();
    }

    public function getName(): string
    {
        $attr = $this->getCommandAttribute();
        return $attr ? $attr->name : '';
    }

    public function getDescription(): string
    {
        $attr = $this->getCommandAttribute();
        return $attr ? $attr->description : '';
    }

    public function getCategory(): string
    {
        $attr = $this->getCommandAttribute();
        return $attr ? $attr->category : 'other';
    }

    private function getCommandAttribute(): ?Command
    {
        $results = new ScannerAttributeManager(static::class)
            ->onClass()
            ->withAttribute(Command::class)
            ->scan();

        return !empty($results) ? $results[0]['attribute'] : null;
    }

    /** @return list<string> */
    protected function getAvailableProjects(): array
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
}
<?php
declare(strict_types=1);

namespace Neo\Core\Console;

use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Output\Output;
use Neo\Core\DI\Container;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionException;

class ConsoleHandler
{
    /** @var array<string, array{instance: AbstractCommand, description: string, category: string}> */
    private array $commands = [];

    public function __construct(private readonly Container $container) {}

    /** @throws ReflectionException */
    private function loadCommands(): void
    {
        $basePaths = [
            __DIR__ . '/../../../src',
            __DIR__ . '/../../../neo',
        ];

        foreach ($basePaths as $basePath) {
            if (!is_dir($basePath)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($basePath)
            );

            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                if (!str_contains($file->getPathname(), DIRECTORY_SEPARATOR . 'Commands' . DIRECTORY_SEPARATOR)) {
                    continue;
                }

                require_once $file->getPathname();
            }
        }

        foreach (get_declared_classes() as $class) {
            if (!is_subclass_of($class, AbstractCommand::class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract()) {
                continue;
            }

            $attributes = $reflection->getAttributes(Command::class);

            if (empty($attributes)) {
                continue;
            }

            $instance = new $class($this->container);
            $instance->configure();

            $name = $instance->getName();
            $description = $instance->getDescription();
            $category = $instance->getCategory();

            if ($name === '') {
                continue;
            }

            $this->commands[$name] = [
                'instance' => $instance,
                'description' => $description,
                'category' => $category,
            ];
        }

        ksort($this->commands);
    }

    /**
     * @param list<string> $argv
     * @throws ReflectionException
     */
    public function run(array $argv): ExitCode
    {
        array_shift($argv);
        $this->loadCommands();

        $commandName = $argv[0] ?? null;

        if ($commandName === null) {
            $this->showHelp();
            return ExitCode::SUCCESS;
        }

        if (!isset($this->commands[$commandName])) {
            Output::error("Unknown command: $commandName");
            Output::newLine();
            $this->showHelp();
            return ExitCode::FAILURE;
        }

        $rawArgs = array_slice($argv, 1);
        $instance = $this->commands[$commandName]['instance'];

        if (in_array('--help', $rawArgs, true) || in_array('-h', $rawArgs, true)) {
            $instance->renderHelp();
            return ExitCode::SUCCESS;
        }

        $input = new Input($rawArgs, $instance->getArgumentDefinitions(), $instance->getOptionDefinitions());

        foreach ($instance->getArgumentDefinitions() as $argDef) {
            if ($argDef->isRequired() && $input->getArgument($argDef->getName()) === null) {
                Output::error("Missing required argument: <{$argDef->getName()}>");
                Output::newLine();
                $instance->renderHelp();
                return ExitCode::FAILURE;
            }
        }

        $output = new Output();

        return $instance->do($input, $output);
    }

    private function showHelp(): void
    {
        Output::title('NeoPHP Console');

        $groups = [];

        foreach ($this->commands as $name => $info) {
            $groups[$info['category']][] = [$name, $info['description']];
        }

        ksort($groups);

        foreach ($groups as $group => $cmds) {
            echo ' '
                . Output::colorize(strtoupper($group), 'yellow') . "\n";

            foreach ($cmds as [$name, $desc]) {
                echo '  '
                    . Output::colorize(str_pad($name, 32), 'cyan')
                    . Output::colorize($desc, 'dim') . "\n";
            }

            Output::newLine();
        }

        echo Output::colorize("Use 'php bin/neo <command> --help' for details.\n", 'dim');
    }
}
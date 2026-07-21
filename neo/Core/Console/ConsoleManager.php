<?php
declare(strict_types=1);

namespace Neo\Core\Console;

use Neo\App;
use Neo\Core\Console\Abstract\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Output\Output;
use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Utils\Scanner\ScannerAttributeManager;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionException;

class ConsoleManager
{
    /** @var list<string> */
    private const array COMMAND_BASE_PATHS = [
        __DIR__ . '/../../../src',
        __DIR__ . '/../../../neo',
    ];

    /** @var array<string, array{instance: AbstractCommand, description: string, category: string}> */
    private array $commands = [];

    public function __construct(private readonly Container $container) {}

    private static function requireCommandFiles(): void
    {
        foreach (self::COMMAND_BASE_PATHS as $basePath) {
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
    }

    /**
     * @throws ReflectionException
     */
    public static function findProjectForCommand(string $commandName): ?string
    {
        self::requireCommandFiles();

        foreach (get_declared_classes() as $class) {
            if (!is_subclass_of($class, AbstractCommand::class)) {
                continue;
            }

            $results = new ScannerAttributeManager($class)
                ->onClass()
                ->withAttribute(Command::class)
                ->scan();

            if (empty($results)) {
                continue;
            }

            /** @var array{reflection: ReflectionClass<object>, attribute: Command} $row */
            $row = $results[0];

            if ($row['reflection']->isAbstract()) {
                continue;
            }

            /** @var Command $attr */
            $attr = $row['attribute'];

            if ($attr->name === $commandName) {
                return $attr->project;
            }
        }

        return null;
    }

    /** @throws ReflectionException */
    private function loadCommands(): void
    {
        self::requireCommandFiles();

        foreach (get_declared_classes() as $class) {
            if (!is_subclass_of($class, AbstractCommand::class)) {
                continue;
            }

            $results = new ScannerAttributeManager($class)
                ->onClass()
                ->withAttribute(Command::class)
                ->scan();

            if (empty($results)) {
                continue;
            }

            /** @var array{reflection: ReflectionClass<object>} $row */
            $row = $results[0];

            if ($row['reflection']->isAbstract()) {
                continue;
            }

            $refClass = $row['reflection'];

            if ($refClass->isAbstract()) {
                continue;
            }

            $instance = $this->container->make($class);
            $instance->configure();

            $name = $instance->getName();

            if ($name === '') {
                continue;
            }

            $this->commands[$name] = [
                'instance' => $instance,
                'description' => $instance->getDescription(),
                'category' => $instance->getCategory(),
            ];
        }

        ksort($this->commands);
    }

    /**
     * @param list<string> $argv
     * @throws ReflectionException
     * @throws ContainerException
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

        if (!$this->container->has('application')) {

            $needsProject = !empty(array_filter(
                $instance->getOptionDefinitions(),
                fn($def) => $def->getName() === 'project'
            ));

            if ($needsProject) {
                $project = $input->getOption('project') ?? Input::choice('Target project ?', $this->getAvailableProjects());
                $GLOBALS['_NEO_CLI_PROJECT'] = $project;

                $app = new App();
                $newContainer = $app->getContainer();

                $instance = $newContainer->make(get_class($instance));
                $instance->configure();

                $input->forceOption('project', $project);
            }
        }

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
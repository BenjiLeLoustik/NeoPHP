<?php
declare(strict_types=1);

namespace Neo\Core\Console;

use Neo\Core\Console\Helper\Output;
use Neo\Core\Console\Interface\CommandInterface;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\DI\Container;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use ReflectionClass;

class ConsoleHandler
{
    private array $commands = [];

    public function __construct(private Container $container)
    {}

    /**
     * @throws \ReflectionException
     */
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

                if (
                    !str_contains($file->getPathname(), DIRECTORY_SEPARATOR . 'Commands' . DIRECTORY_SEPARATOR)
                )
                {
                    continue;
                }

                require_once $file->getPathname();
            }
        }

        foreach (get_declared_classes() as $class) {
            if (!is_subclass_of($class, CommandInterface::class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            $attributes = $reflection->getAttributes(Command::class);

            if (empty($attributes)) {
                continue;
            }

            $attribute = $attributes[0]->newInstance();
            $instance = new $class($this->container);
            $name = $attribute->name ?? $instance->getName();
            $description = $attribute->description ?? $instance->getDescription();
            $category = $attribute->category ?? 'other';

            $this->commands[$name] = [
                'instance' => $instance,
                'description' => $description,
                'category' => $category
            ];
        }

        ksort($this->commands);
    }

    /**
     * @throws \ReflectionException
     */
    public function run(array $argv): void
    {
        array_shift($argv);
        $this->loadCommands();

        $commandName = $argv[0] ?? null;

        if ($commandName === null) {
            $this->showHelp();
            return;
        }

        if (!isset($this->commands[$commandName])) {
            Output::error("Unknown command: $commandName");
            Output::newLine();
            $this->showHelp();
            return;
        }

        $instance = $this->commands[$commandName]['instance'];
        $args = array_slice($argv, 1);

        if (in_array('--help', $args, true) || in_array('-h', $args, true)) {
            echo $instance->getHelp() . "\n";
            return;
        }

        $instance->execute($args);
    }

    private function showHelp(): void
    {
        Output::title('NeoPHP Console');
        $groups = [];

        foreach ($this->commands as $name => $info) {
            $group = $info['category'];
            $groups[$group][] = [$name, $info['description']];
        }

        ksort($groups);

        foreach ($groups as $group => $cmds) {
            echo ' ' . Output::colorize(strtoupper($group), 'yellow') . "\n";
            foreach ($cmds as [$name, $desc]) {
                echo '  ' . Output::colorize(str_pad($name, 32), 'cyan') . Output::colorize($desc, 'dim') . "\n";
            }
            Output::newLine();
        }

        echo Output::colorize("Use 'php bin/neo <command> --help' for details.\n", 'dim');
    }
}

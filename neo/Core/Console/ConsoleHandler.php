<?php
declare(strict_types=1);

namespace Neo\Core\Console;

use Neo\Core\Console\Helper\Output;
use Neo\Core\Console\Interface\CommandInterface;
use Neo\Core\Console\Attribute\Command as CommandAttribute;
use Neo\Core\DI\Container;
use Neo\Core\Tools\Scanner\AttributeScanner;

class ConsoleHandler
{
    private array $commands = [];

    public function __construct(private Container $container)
    {}

    private function loadCommands(): void
    {
        $basePaths = [
            __DIR__ . '/../../../src',
            __DIR__ . '/../../../neo',
        ];

        $scanner = AttributeScanner::scan(CommandAttribute::class)
            ->withSuffix('.php')
            ->onClasses();

        foreach ($basePaths as $basePath) {
            if (is_dir($basePath)) {
                $scanner->inSubfolder($basePath, 'Commands');
            }
        }

        foreach ($scanner->getResults() as $scannedCommand) {
            /** @var \ReflectionClass $reflection */
            $reflection = $scannedCommand['class'];
            /** @var CommandAttribute $attribute */
            $attribute = $scannedCommand['attribute'];

            $class = $reflection->getName();

            if (!$reflection->implementsInterface(CommandInterface::class)) {
                continue;
            }

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
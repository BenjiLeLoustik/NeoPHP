<?php
declare(strict_types=1);

namespace Neo\Core\Console;

use Neo\Core\Console\Interface\CommandInterface;
use Neo\Core\Console\Attribute\Command;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use ReflectionClass;

class ConsoleHandler
{
    private array $commands = [];

    public function __construct()
    {
        $this->loadCommands();
    }

    private function loadCommands(): void
    {
        $commandPath = __DIR__ . '/Commands';

        if (!is_dir($commandPath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($commandPath)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            require_once $file->getPathname();
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
            $instance = new $class();

            $name = $attribute->name ?? $instance->getName();
            $description = $attribute->description ?? $instance->getDescription();

            $this->commands[$name] = [
                'instance' => $instance,
                'description' => $description
            ];
        }
    }

    public function run(array $argv): void
    {
        array_shift($argv);

        $commandName = $argv[0] ?? null;

        if ($commandName === null) {
            $this->showHelp();
            return;
        }

        if (!isset($this->commands[$commandName])) {
            echo "Commande inconnue : $commandName\n\n";
            $this->showHelp();
            return;
        }

        $instance = $this->commands[$commandName]['instance'];
        $args = array_slice($argv, 1);

        if (in_array('--help', $args, true) || in_array('-h', $args, true)) {
            if (method_exists($instance, 'getHelp')) {
                echo $instance->getHelp() . "\n";
            } else {
                echo "Aucune aide disponible pour la commande : $commandName\n";
            }
            return;
        }

        $instance->execute($args);
    }

    private function showHelp(): void
    {
        echo "Liste des commandes disponibles :\n\n";
        foreach ($this->commands as $name => $info) {
            echo "  $name   => " . $info['description'] . "\n";
        }
        echo "\nUtilisez 'php bin/neo <commande> --help' pour plus de détails.\n";
    }
}

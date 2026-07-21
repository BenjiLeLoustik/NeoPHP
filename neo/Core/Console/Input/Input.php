<?php
declare(strict_types=1);

namespace Neo\Core\Console\Input;

use Neo\Core\Console\Output\Output;

final class Input
{
    /** @var array<string, mixed> */
    private array $arguments = [];

    /** @var array<string, mixed> */
    private array $options = [];

    /** @var list<InputArgument> */
    private array $argumentDefinitions = [];

    /** @var array<string, InputOption> */
    private array $optionDefinitions = [];

    /**
     * @param list<string> $argv
     * @param list<InputArgument> $argumentDefs
     * @param list<InputOption> $optionDefs
     */
    public function __construct(
        array $argv,
        array $argumentDefs = [],
        array $optionDefs = [],
    ) {
        $this->argumentDefinitions = $argumentDefs;

        foreach ($optionDefs as $def) {
            $this->optionDefinitions[$def->getName()] = $def;

            if ($def->getShortcut() !== null) {
                $this->optionDefinitions[$def->getShortcut()] = $def;
            }
        }

        $this->parse($argv);
    }

    /**
     * @param list<string> $argv
     */
    private function parse(array $argv): void
    {
        $positionalValues = [];
        $count = count($argv);

        for ($i = 0; $i < $count; $i++) {
            $token = $argv[$i];

            if (str_starts_with($token, '--') && str_contains($token, '=')) {
                [$key, $val] = explode('=', ltrim($token, '-'), 2);
                $this->setOption($key, $val);
                continue;
            }

            if (str_starts_with($token, '--')) {
                $key = ltrim($token, '-');
                $def = $this->optionDefinitions[$key] ?? null;
                $next = $argv[$i + 1] ?? null;

                if ($def !== null && $def->requiresValue() && $next !== null && !str_starts_with($next, '-')) {
                    $this->setOption($key, $next);
                    $i++;
                } else {
                    $this->setOption($key, true);
                }

                continue;
            }

            if (str_starts_with($token, '-') && strlen($token) === 2) {
                $key = ltrim($token, '-');
                $def = $this->optionDefinitions[$key] ?? null;
                $next = $argv[$i + 1] ?? null;

                if ($def !== null && $def->requiresValue() && $next !== null && !str_starts_with($next, '-')) {
                    $canonical = $def->getName();
                    $this->setOption($canonical, $next);
                    $i++;
                } else {
                    $canonical = $def?->getName() ?? $key;
                    $this->setOption($canonical, true);
                }

                continue;
            }

            $positionalValues[] = $token;
        }

        $defIndex = 0;

        foreach ($positionalValues as $value) {
            $def = $this->argumentDefinitions[$defIndex] ?? null;

            if ($def === null) {
                break;
            }

            if ($def->isArray()) {
                if (!isset($this->arguments[$def->getName()])) {
                    $this->arguments[$def->getName()] = [];
                }
                $this->arguments[$def->getName()][] = $value;
            } else {
                $this->arguments[$def->getName()] = $value;
                $defIndex++;
            }
        }

        foreach ($this->argumentDefinitions as $def) {
            if (!array_key_exists($def->getName(), $this->arguments)) {
                $this->arguments[$def->getName()] = $def->isArray() ? [] : $def->getDefault();
            }
        }

        foreach ($this->optionDefinitions as $name => $def) {
            if ($name !== $def->getName()) {
                continue;
            }

            if (!array_key_exists($def->getName(), $this->options)) {
                $this->options[$def->getName()] = $def->isFlag() ? false : $def->getDefault();
            }
        }
    }

    private function setOption(string $key, mixed $value): void
    {
        $def = $this->optionDefinitions[$key] ?? null;
        $canonical = $def?->getName() ?? $key;

        if ($def !== null && $def->isArray()) {
            if (!isset($this->options[$canonical])) {
                $this->options[$canonical] = [];
            }
            $this->options[$canonical][] = $value;
        } else {
            $this->options[$canonical] = $value;
        }
    }

    public function forceOption(string $name, mixed $value): void
    {
        $def = $this->optionDefinitions[$name] ?? null;
        $canonical = $def?->getName() ?? $name;
        $this->options[$canonical] = $value;
    }

    public function getArgument(string $name): mixed
    {
        return $this->arguments[$name] ?? null;
    }

    public function getOption(string $name): mixed
    {
        $def = $this->optionDefinitions[$name] ?? null;
        $canonical = $def?->getName() ?? $name;

        return $this->options[$canonical] ?? null;
    }

    public function hasOption(string $name): bool
    {
        $def = $this->optionDefinitions[$name] ?? null;
        $canonical = $def?->getName() ?? $name;

        $value = $this->options[$canonical] ?? null;

        return $value !== null && $value !== false;
    }

    public static function ask(string $question, ?string $default = null): string
    {
        $hint = $default !== null
            ? Output::colorize(" [{$default}]", 'dim')
            : '';

        echo Output::colorize($question, 'bold')
            . $hint
            . \Neo\Core\Console\Output\Output::colorize(' : ', 'cyan');

        $answer = trim(fgets(STDIN));

        return $answer !== '' ? $answer : ($default ?? '');
    }

    public static function confirm(string $question, bool $default = false): bool
    {
        $hint = $default
            ? Output::colorize(' [Y/n]', 'dim')
            : Output::colorize(' [y/N]', 'dim');

        echo Output::colorize($question, 'bold')
            . $hint
            . Output::colorize(' : ', 'cyan');

        $answer = strtolower(trim(fgets(STDIN)));

        if ($answer === '') {
            return $default;
        }

        return in_array($answer, ['y', 'yes', 'o', 'oui'], true);
    }

    /**
     * @param list<string> $choices
     */
    public static function choice(string $question, array $choices, ?string $default = null): string
    {
        echo Output::colorize($question, 'bold') . "\n";

        foreach ($choices as $i => $choice) {
            $number = Output::colorize('  [' . ($i + 1) . '] ', 'dim');

            if ($default === $choice) {
                echo $number .
                    Output::colorize($choice, 'green') .
                    Output::colorize(' (default)', 'dim') . "\n";
            } else {
                echo $number . $choice . "\n";
            }
        }

        echo Output::colorize('  › ', 'cyan');
        $answer = trim(fgets(STDIN));

        if ($answer === '' && $default !== null) {
            return $default;
        }

        $index = ((int) $answer) - 1;

        if (!isset($choices[$index])) {
            Output::error('Invalid choice.');
            return self::choice($question, $choices, $default);
        }

        return $choices[$index];
    }

    /**
     * @param list<string> $choices
     * @return list<string>
     */
    public static function multiChoice(string $question, array $choices): array
    {
        echo Output::colorize($question, 'bold')
            . "\n";

        echo Output::colorize('  (separate multiple choices with commas, e.g. 1,3)', 'dim')
            . "\n";

        foreach ($choices as $i => $choice) {
            echo Output::colorize('  [' . ($i + 1) . '] ', 'dim')
                . $choice . "\n";
        }

        echo Output::colorize('  › ', 'cyan');
        $answer = trim(fgets(STDIN));

        if ($answer === '') {
            return [];
        }

        $selected = [];

        foreach (explode(',', $answer) as $raw) {
            $index = ((int) trim($raw)) - 1;

            if (isset($choices[$index])) {
                $selected[] = $choices[$index];
            }
        }

        return $selected;
    }

    public static function secret(string $question): string
    {
        echo Output::colorize($question, 'bold')
            . Output::colorize(' : ', 'cyan');

        if (DIRECTORY_SEPARATOR === '\\') {
            return trim(fgets(STDIN));
        }

        system('stty -echo');
        $value = trim(fgets(STDIN));
        system('stty echo');
        echo "\n";

        return $value;
    }

    /**
     * @param list<string> $suggestions
     */
    public static function autocomplete(string $question, array $suggestions, ?string $default = null): string
    {
        $hint = $default !== null
            ? Output::colorize(" [{$default}]", 'dim')
            : '';

        echo Output::colorize($question, 'bold') . $hint . "\n";

        echo Output::colorize('  Suggestions : ', 'dim')
            . Output::colorize(implode(', ', $suggestions), 'dim') . "\n";

        echo Output::colorize('  › ', 'cyan');

        $answer = trim(fgets(STDIN));

        if ($answer === '') {
            return $default ?? '';
        }

        if (in_array($answer, $suggestions, true)) {
            return $answer;
        }

        foreach ($suggestions as $suggestion) {
            if (str_starts_with(strtolower($suggestion), strtolower($answer))) {
                return $suggestion;
            }
        }

        return $answer;
    }
}
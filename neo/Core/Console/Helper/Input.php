<?php

namespace Neo\Core\Console\Helper;

final class Input
{
    public static function ask(string $question, ?string $default = null): string
    {
        $hint = $default !== null ? " [{$default}] " : '';
        echo Output::colorize("{$question}{$hint} : ", 'cyan');
        $answer = trim(fgets(STDIN));

        return $answer !== ''
            ? $answer
            : ($default ?? '');
    }

    public static function confirm(string $question, bool $default = null): bool
    {
        $hint = $default ? '[Y/n]' : '[y/N]';
        echo Output::colorize("? {$question} {$hint} : ", 'cyan');
        $answer = trim(fgets(STDIN));

        if ($answer === '') {
            return $default;
        }

        return in_array($answer, ['y', 'yes'], true);
    }

    public static function choice(string $question, array $choices, ?string $default = null): string
    {
        echo Output::colorize("? {$question}", 'cyan') . "\n";

        $indexed = array_values($choices);

        foreach ($indexed as $i => $choice) {
            $marker = ($default === $choice)
                ? Output::colorize("  [" . ($i + 1) . "] ", 'green') . Output::colorize($choice, 'green')
                : Output::colorize("  [" . ($i + 1) . "] ", 'dim') . $choice;
            echo $marker . "\n";
        }

        echo Output::colorize('  Your choice : ', 'cyan');
        $answer = trim(fgets(STDIN));

        if ($answer === '' && $default !== null) {
            return $default;
        }

        $index = ((int) $answer) - 1;

        if (!isset($indexed[$index])) {
            Output::error('Invalid choice.');
            return self::choice($question, $choices, $default);
        }

        return $indexed[$index];
    }

    public static function multiChoice(string $question, array $choices): array
    {
        echo Output::colorize("? {$question}", 'cyan') . "\n";
        Output::muted('  (separate multiple choices with commas, e.g. 1,3)');

        $indexed = array_values($choices);

        foreach ($indexed as $i => $choice) {
            echo Output::colorize("  [" . ($i + 1) . "] ", 'dim') . $choice . "\n";
        }

        echo Output::colorize('  Your choices : ', 'cyan');
        $answer = trim(fgets(STDIN));

        if ($answer === '') {
            return [];
        }

        $selected = [];

        foreach (explode(',', $answer) as $raw) {
            $index = ((int) trim($raw)) - 1;

            if (isset($indexed[$index])) {
                $selected[] = $indexed[$index];
            }
        }

        return $selected;
    }

    public static function secret(string $question): string
    {
        echo Output::colorize("? {$question} : ", 'cyan');

        if (DIRECTORY_SEPARATOR === '\\') {
            return trim(fgets(STDIN));
        }

        system('stty -echo');
        $value = trim(fgets(STDIN));
        system('stty echo');
        echo "\n";

        return $value;
    }

    public static function autocomplete(string $question, array $suggestions, ?string $default = null): string
    {
        $hint = $default !== null ? " [{$default}]" : '';
        echo Output::colorize("? {$question}{$hint}", 'cyan') . "\n";
        echo Output::colorize('  Suggestions : ', 'dim') . implode(', ', $suggestions) . "\n";
        echo Output::colorize('  Your answer : ', 'cyan');

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
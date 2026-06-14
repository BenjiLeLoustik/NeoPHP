<?php

namespace Neo\Core\Console\Helper;

final class Input
{
    public static function ask(string $question, ?string $default = null): string
    {
        $hint = $default !== null
            ? Output::colorize(" [{$default}]", 'dim')
            : '';

        echo Output::colorize($question, 'bold') . $hint . Output::colorize(' : ', 'cyan');
        $answer = trim(fgets(STDIN));

        return $answer !== '' ? $answer : ($default ?? '');
    }

    public static function confirm(string $question, bool $default = false): bool
    {
        $hint = $default
            ? Output::colorize(' [Y/n]', 'dim')
            : Output::colorize(' [y/N]', 'dim');

        echo Output::colorize($question, 'bold') . $hint . Output::colorize(' : ', 'cyan');
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
        echo Output::colorize($question, 'bold') . "\n";
        echo Output::colorize('  (separate multiple choices with commas, e.g. 1,3)', 'dim') . "\n";

        foreach ($choices as $i => $choice) {
            echo Output::colorize('  [' . ($i + 1) . '] ', 'dim') . $choice . "\n";
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
        echo Output::colorize($question, 'bold') . Output::colorize(' : ', 'cyan');

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
        echo Output::colorize('  Suggestions : ', 'dim') .
            Output::colorize(implode(', ', $suggestions), 'dim') . "\n";
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
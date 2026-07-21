<?php
declare(strict_types=1);

namespace Neo\Core\Translation\Commands;

use Neo\Core\Console\Abstract\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Output\Output;
use Neo\Core\Translation\Domain\TranslationDomain;

#[Command(
    name: 'translation:sync',
    description: 'Sync translation files with keys found in the project',
    category: 'Translation'
)]
final class TranslationSyncCommand extends AbstractCommand
{
    public function configure(): void
    {
        $this->addOption(
            name: 'project',
            mode: InputOption::REQUIRED,
            description: 'Project to sync translations for'
        );

        $this->addOption(
            name: 'dry-run',
            mode: InputOption::NONE,
            description: 'Show what would be added/removed without writing'
        );

        $this->addOption(
            name: 'prune',
            mode: InputOption::NONE,
            description: 'Actually remove keys that are no longer found in source files (destructive)'
        );
    }

    public function do(Input $input, Output $output): ExitCode
    {
        $project = $input->getOption('project');

        $dryRun = (bool) $input->getOption('dry-run');
        $prune  = (bool) $input->getOption('prune');
        $path = ROOT_DIR . "src/$project/Translations";

        if (!is_dir($path)) {
            Output::error("Translations folder not found for project '$project'.");
            return ExitCode::FAILURE;
        }

        $srcPath = ROOT_DIR . "src/$project";
        $keysByDomain = $this->extractKeys($srcPath);

        if (empty($keysByDomain)) {
            Output::warning('No translation keys found.');
            return ExitCode::SUCCESS;
        }

        $totalKeys = array_sum(array_map('count', $keysByDomain));
        Output::info("$totalKeys key(s) found across " . count($keysByDomain) . ' domain(s).');

        $locales = $this->discoverLocales($project, $path);

        if (empty($locales)) {
            Output::warning("No locale could be determined for '$project'. Check 'translation.available_locales' in app.config.php.");
            return ExitCode::SUCCESS;
        }

        Output::muted('Locales: ' . implode(', ', $locales));

        foreach ($keysByDomain as $domain => $keys) {
            foreach ($locales as $locale) {
                $filePath = TranslationDomain::resolveFilePath($path, $locale, $domain);
                $translations = file_exists($filePath) ? (require $filePath) : [];

                if (!is_array($translations)) {
                    Output::error("File $filePath does not return an array, skipping.");
                    continue;
                }

                $existing = array_keys($translations);
                $toAdd = array_diff($keys, $existing);
                $toRemove = array_diff($existing, $keys);

                Output::muted("[$domain/$locale] +" . count($toAdd) . ' / -' . count($toRemove));

                foreach ($toAdd as $key) {
                    Output::success("  + '$key'");
                }

                foreach ($toRemove as $key) {
                    Output::warning("  - '$key'" . ($prune ? '' : ' (not removed, use --prune to delete)'));
                }

                if ($dryRun) {
                    continue;
                }

                foreach ($toAdd as $key) {
                    $translations[$key] = $key;
                }

                if ($prune) {
                    foreach ($toRemove as $key) {
                        unset($translations[$key]);
                    }
                }

                $this->dumpPhpFile($filePath, $translations);

                if (function_exists('opcache_invalidate')) {
                    opcache_invalidate($filePath, true);
                }
            }
        }

        if ($dryRun) {
            Output::muted('Dry-run mode, nothing written.');
        } else {
            Output::success('Translation files synced.');
        }

        return ExitCode::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function discoverLocales(string $project, string $translationsPath): array
    {
        $configPath = ROOT_DIR . "src/$project/Config/app.config.php";

        if (is_file($configPath)) {
            $config = require $configPath;

            $availableLocales = $config['translation']['available_locales'] ?? null;

            if (is_array($availableLocales) && !empty($availableLocales)) {
                return array_values(array_map('strval', array_keys($availableLocales)));
            }
        }

        return $this->discoverLocalesFromDisk($translationsPath);
    }

    /**
     * @return list<string>
     */
    private function discoverLocalesFromDisk(string $path): array
    {
        $locales = [];

        foreach (glob($path . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $locales[] = basename($dir);
        }

        return array_values(array_unique($locales));
    }

    /**
     * @return array<string, list<string>> Keys grouped by domain
     */
    private function extractKeys(string $srcPath): array
    {
        $keysByDomain = [];

        $targs = '(?:'
            . '\'(?:\\\\.|[^\'\\\\])*\''
            . '|"(?:\\\\.|[^"\\\\])*"'
            . '|\((?&targs)*\)'
            . '|\[(?&targs)*\]'
            . '|\{(?&targs)*\}'
            . '|[^()\[\]{}\'"]'
            . ')*';

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($srcPath, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (!in_array($file->getExtension(), ['php', 'twig'], true)) {
                continue;
            }

            if (str_contains($file->getPathname(), DIRECTORY_SEPARATOR . 'Translations' . DIRECTORY_SEPARATOR)) {
                continue;
            }

            $content = file_get_contents($file->getPathname());

            preg_match_all(
                '/(?<![a-zA-Z0-9_])(?:translate|trans)\((?P<targs>' . $targs . ')\)/us',
                $content,
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $match) {
                $result = $this->extractTranslationArgs($match['targs'], keyInArgs: true);

                if ($result['key'] === null) {
                    continue;
                }

                $keysByDomain[$result['domain']][] = $result['key'];
            }

            preg_match_all(
                '/([\'"])((?:\\\\.|(?!\1).)*)\1\|trans(?:\((?P<targs>' . $targs . ')\))?/us',
                $content,
                $filterMatches,
                PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL
            );

            foreach ($filterMatches as $match) {
                $key = $this->unescapeString($match[2]);
                $result = $this->extractTranslationArgs($match['targs'] ?? '', keyInArgs: false);
                $keysByDomain[$result['domain']][] = $key;
            }

            preg_match_all(
                '/=>\s*([\'"])((?:\\\\.|(?!\1).)*)\1\s*,?\s*\/\/\s*@translatable(?::(\w+))?/u',
                $content,
                $configMatches,
                PREG_SET_ORDER
            );

            foreach ($configMatches as $match) {
                $key = $this->unescapeString($match[2]);
                $domain = $match[3] ?? TranslationDomain::DEFAULT;
                $keysByDomain[$domain][] = $key;
            }
        }

        foreach ($keysByDomain as $domain => $keys) {
            $keysByDomain[$domain] = array_values(array_unique($keys));
        }

        return $keysByDomain;
    }

    /**
     * @return list<string>
     */
    private function splitTopLevelArgs(string $argsBlob): array
    {
        $blob = ltrim($argsBlob);
        $blob = ltrim($blob, ',');

        $args = [];
        $current = '';
        $depth = 0;
        $inQuote = null;
        $len = strlen($blob);

        for ($i = 0; $i < $len; $i++) {
            $char = $blob[$i];

            if ($inQuote !== null) {
                $current .= $char;
                if ($char === '\\') {
                    if ($i + 1 < $len) {
                        $current .= $blob[++$i];
                    }
                    continue;
                }
                if ($char === $inQuote) {
                    $inQuote = null;
                }
                continue;
            }

            if ($char === '\'' || $char === '"') {
                $inQuote = $char;
                $current .= $char;
                continue;
            }

            if ($char === '(' || $char === '[' || $char === '{') {
                $depth++;
                $current .= $char;
                continue;
            }

            if ($char === ')' || $char === ']' || $char === '}') {
                $depth--;
                $current .= $char;
                continue;
            }

            if ($char === ',' && $depth === 0) {
                $args[] = $current;
                $current = '';
                continue;
            }

            $current .= $char;
        }

        if (trim($current) !== '') {
            $args[] = $current;
        }

        return $args;
    }

    /**
     * @return array{named: array<string, string>, positional: list<string>}
     */
    private function parseCallArguments(string $argsBlob): array
    {
        $named = [];
        $positional = [];

        foreach ($this->splitTopLevelArgs($argsBlob) as $rawArg) {
            $arg = trim($rawArg);

            if ($arg === '') {
                continue;
            }

            if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*:\s*(?!:)(.*)$/us', $arg, $m)) {
                $named[$m[1]] = trim($m[2]);
                continue;
            }

            $positional[] = $arg;
        }

        return ['named' => $named, 'positional' => $positional];
    }

    private function extractLiteralString(?string $expr): ?string
    {
        if ($expr === null) {
            return null;
        }

        if (preg_match('/^([\'"])((?:\\\\.|(?!\1).)*)\1$/us', trim($expr), $m)) {
            return $this->unescapeString($m[2]);
        }

        return null;
    }

    /**
     * @return array{key: ?string, domain: string}
     */
    private function extractTranslationArgs(string $argsBlob, bool $keyInArgs): array
    {
        $parsed = $this->parseCallArguments($argsBlob);
        $named = $parsed['named'];
        $positional = $parsed['positional'];

        $key = null;

        if ($keyInArgs) {
            $keyExpr = $named['text'] ?? $named['key'] ?? ($positional[0] ?? null);
            $key = $this->extractLiteralString($keyExpr);
            $domainPositionalIndex = 2;
        } else {
            $domainPositionalIndex = 1;
        }

        $domainExpr = $named['domain'] ?? ($positional[$domainPositionalIndex] ?? null);
        $domain = $this->extractLiteralString($domainExpr) ?? TranslationDomain::DEFAULT;

        return ['key' => $key, 'domain' => $domain];
    }

    private function unescapeString(string $raw): string
    {
        return preg_replace_callback('/\\\\(.)/', static fn(array $m): string => $m[1], $raw);
    }

    /**
     * @param array<string, string> $translations
     */
    private function dumpPhpFile(string $filePath, array $translations): void
    {
        $dir = dirname($filePath);

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            Output::error("Unable to create translation directory '$dir'.");
            return;
        }

        $lines = [];
        foreach ($translations as $key => $value) {
            $lines[] = '    ' . var_export($key, true) . ' => ' . var_export($value, true);
        }

        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n" . implode(",\n", $lines) . "\n];\n";

        file_put_contents($filePath, $content, LOCK_EX);
    }
}
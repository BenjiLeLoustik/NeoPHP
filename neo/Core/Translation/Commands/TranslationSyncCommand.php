<?php
declare(strict_types=1);

namespace Neo\Core\Translation\Commands;

use Neo\Core\Console\Abstract\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Output\Output;
use Neo\Core\Translation\TranslationRegistry;

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

        if (!$project) {
            $projects = $this->getAvailableProjects();
            if (empty($projects)) {
                Output::error('No projects found.');
                return ExitCode::FAILURE;
            }
            $project = Input::choice('Which project do you want to sync?', $projects);
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $prune = (bool) $input->getOption('prune');
        $path = ROOT_DIR . "src/$project/Translations";

        if (!is_dir($path)) {
            Output::error("Translations folder not found for project '$project'.");
            return ExitCode::FAILURE;
        }

        $srcPath = ROOT_DIR . "src/$project";

        $keys = $this->extractKeys($srcPath);

        if (empty($keys)) {
            Output::warning('No translation keys found.');
            return ExitCode::SUCCESS;
        }

        Output::info(count($keys) . ' key(s) found in source files.');

        $localeFiles = glob($path . '/*.php') ?: [];

        if (empty($localeFiles)) {
            Output::warning('No locale files found in ' . $path);
            return ExitCode::SUCCESS;
        }

        foreach ($localeFiles as $localeFile) {
            $locale  = basename($localeFile, '.php');
            $translations = require $localeFile;

            if (!is_array($translations)) {
                Output::error("File $localeFile does not return an array, skipping.");
                continue;
            }

            $existing = array_keys($translations);
            $toAdd = array_diff($keys, $existing);
            $toRemove = array_diff($existing, $keys);

            Output::muted("[$locale] +" . count($toAdd) . ' / -' . count($toRemove));

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

            $this->dumpPhpFile($localeFile, $translations);

            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($localeFile, true);
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
    private function extractKeys(string $srcPath): array
    {
        $keys = [];
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
                '/(?<![a-zA-Z0-9_])(?:translate|trans)\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1/us',
                $content,
                $matches
            );

            preg_match_all(
                '/([\'"])((?:\\\\.|(?!\1).)*)\1\|trans/us',
                $content,
                $filterMatches
            );

            preg_match_all(
                '/=>\s*([\'"])((?:\\\\.|(?!\1).)*)\1\s*,?\s*\/\/\s*@translatable/u',
                $content,
                $configMatches
            );

            foreach ($matches[2] as $key) {
                $keys[] = $this->unescapeString($key);
            }

            foreach ($filterMatches[2] as $key) {
                $keys[] = $this->unescapeString($key);
            }

            foreach ($configMatches[2] as $key) {
                $keys[] = $this->unescapeString($key);
            }
        }

        return array_values(array_unique($keys));
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
        $lines = [];
        foreach ($translations as $key => $value) {
            $lines[] = '    ' . var_export($key, true) . ' => ' . var_export($value, true);
        }

        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n" . implode(",\n", $lines) . "\n];\n";

        file_put_contents($filePath, $content, LOCK_EX);
    }
}
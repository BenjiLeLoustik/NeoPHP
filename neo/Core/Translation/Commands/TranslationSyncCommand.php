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
                Output::warning("  - '$key'");
            }

            if ($dryRun) {
                continue;
            }

            foreach ($toAdd as $key) {
                $translations[$key] = $key;
            }

            foreach ($toRemove as $key) {
                unset($translations[$key]);
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

            $content = file_get_contents($file->getPathname());

            preg_match_all(
                '/(?:translate|trans)\(\s*[\'"](.+?)[\'"]/u',
                $content,
                $matches
            );

            preg_match_all(
                '/[\'"](.+?)[\'"]\|trans/u',
                $content,
                $filterMatches
            );

            foreach ($matches[1] as $key) {
                $keys[] = $key;
            }

            foreach ($filterMatches[1] as $key) {
                $keys[] = $key;
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param array<string, string> $translations
     */
    private function dumpPhpFile(string $filePath, array $translations): void
    {
        $lines = [];
        foreach ($translations as $key => $value) {
            $k = str_replace("'", "\\'", $key);
            $v = str_replace("'", "\\'", $value);
            $lines[] = "    '$k' => '$v'";
        }

        $content = "<?php\n\nreturn [\n" . implode(",\n", $lines) . "\n];\n";

        file_put_contents($filePath, $content, LOCK_EX);
    }
}
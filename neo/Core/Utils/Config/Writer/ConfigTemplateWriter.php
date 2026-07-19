<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Config\Writer;

use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Output\Output;
use Neo\Core\Utils\Config\Templates\Interface\ConfigTemplateInterface;

final class ConfigTemplateWriter
{
    /**
     * @param ConfigTemplateInterface[] $templates
     * @param array<string, mixed> $context
     */
    public static function write(
        array $templates,
        string $configPath,
        string $projectName,
        array $context = [],
        bool $askOverwrite = true,
    ): void {
        foreach ($templates as $template) {
            $filename = $template->filename();
            $fullPath = $configPath . $filename;

            if ($askOverwrite && file_exists($fullPath) && !Input::confirm("$filename exists. Overwrite ?", false)) {
                Output::skip($filename);
                continue;
            }

            file_put_contents($fullPath, $template->render($projectName, $context));
            Output::success("$filename generated.");
        }
    }
}
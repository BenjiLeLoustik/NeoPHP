<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Config\Templates\Interface;

interface ConfigTemplateInterface
{
    public function filename(): string;

    /**
     * @param array<string, mixed> $context
     */
    public function render(string $projectName, array $context = []): string;
}
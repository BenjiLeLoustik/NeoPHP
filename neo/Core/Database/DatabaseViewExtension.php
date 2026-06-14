<?php
declare(strict_types=1);

namespace Neo\Core\Database;

use Neo\Core\View\Interface\TwigExtensionInterface;

final class DatabaseViewExtension implements TwigExtensionInterface
{
    /**
     * @return array<string, array{callable: callable, options: array<string, mixed>}>
     */
    public function getFunctions(): array
    {
        return [
            'database' => [
                'callable' => fn() => DatabaseConnection::isConnected() ? 'On' : 'Off',
                'options' => [],
            ],
        ];
    }

    /**
     * @return array<string, array{callable: callable, options: array<string, mixed>}>
     */
    public function getFilters(): array
    {
        return [];
    }
}
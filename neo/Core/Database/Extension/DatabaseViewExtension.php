<?php
declare(strict_types=1);

namespace Neo\Core\Database\Extension;

use Neo\Core\Database\Connection\DatabaseConnection;
use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\View\Interface\TwigExtensionInterface;

#[Extension(type: ExtensionTypeEnum::VIEW)]
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
            'database_connections' => [
                'callable' => fn() => DatabaseConnection::getConnectionNames(),
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
<?php

declare(strict_types=1);

namespace Neo\Core\Tools\Debug\Extension;

use Neo\Core\DI\ContainerRegistry;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\Tools\Debug\Dumper;
use Neo\Core\Utils\Config\ConfigManager;
use Neo\Core\View\Interface\TwigExtensionInterface;

#[Extension(type: ExtensionTypeEnum::VIEW)]
class DumperTwigExtension implements TwigExtensionInterface
{
    /**
     * @return array<string, array{callable: callable, options: array<string, mixed>}>
     */
    public function getFunctions(): array
    {
        return [
            'var_dump' => [
                'callable' => fn (mixed ...$vars) => $this->render($vars),
                'options' => ['is_safe' => ['html']],
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

    /**
     * @param list<mixed> $vars
     */
    private function render(array $vars): string
    {
        try {
            $env = $this->resolveEnvironment();
        } catch (\Throwable) {
            return '';
        }

        if ($env !== 'dev') {
            return '';
        }

        return new Dumper()->render($vars);
    }

    /**
     * @throws \ReflectionException
     * @throws ContainerException
     */
    private function resolveEnvironment(): string
    {
        return ContainerRegistry::get()
            ->get(ConfigManager::class)
            ->from('app')
            ->get('environment') ?? 'prod';
    }
}
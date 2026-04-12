<?php
declare(strict_types=1);

namespace Neo\Core\Utils;

use Neo\Core\DI\Container;
use Neo\Core\Error\Exception\FrameworkException;

class Config
{
    private const CONFIG_EXTENSION = '.config.php';

    private array $configs = [];
    private array $current = [];
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->loadConfigurations($this->container->get('configsPath'));
    }

    private function loadConfigurations(string $configDir): void
    {
        $pattern = rtrim($configDir, '/\\') . DIRECTORY_SEPARATOR . '*' . self::CONFIG_EXTENSION;
        $files = glob($pattern);

        foreach ($files as $file) {
            $key = basename($file, self::CONFIG_EXTENSION);
            $data = require $file;

            if (!is_array($data)) {
                throw new FrameworkException(
                    title: "Invalid Config File",
                    message: "Config file '{$file}' must return an array.",
                    code: 500,
                    context: ['file' => $file]
                );
            }

            $this->configs[$key] = $data;
        }
    }

    public function from(string $key): self
    {
        if (!isset($this->configs[$key])) {
            throw new FrameworkException(
                title: "Config Not Found",
                message: "Config '{$key}' not found.",
                code: 404,
                context: ['key' => $key]
            );
        }

        $this->current = $this->configs[$key];
        return $this;
    }

    public function get(string $path, mixed $default = null): mixed
    {
        $this->assertSelected();

        $value = $this->current;

        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function all(): array
    {
        $this->assertSelected();
        return $this->current;
    }

    private function assertSelected(): void
    {
        if (empty($this->current)) {
            throw new FrameworkException(
                title: "Config Not Selected",
                message: "Call from() before calling get() or all().",
                code: 500
            );
        }
    }
}

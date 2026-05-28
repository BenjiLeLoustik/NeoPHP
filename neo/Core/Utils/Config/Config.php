<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Config;

use Neo\Core\DI\Container;
use Neo\Core\Utils\Config\Exception\ConfigException;

class Config
{
    private const CONFIG_EXTENSION = '.config.php';
    private const CONFIG_TEST_EXTENSION = '.config.test.php';

    private array $configs = [];
    private array $current = [];
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->loadConfigurations($this->container->get('configsPath'));

        if ($this->container->has('testConfigsPath')) {
            $testConfigsPath = $this->container->get('testConfigsPath');
            if (is_string($testConfigsPath) && is_dir($testConfigsPath)) {
                $this->loadTestConfiguration($testConfigsPath);
            }
        }
    }

    private function loadConfigurations(string $configDir): void
    {
        $pattern = rtrim($configDir, '/\\') . DIRECTORY_SEPARATOR . '*' . self::CONFIG_EXTENSION;
        $files = glob($pattern);

        foreach ($files as $file) {
            $key = basename($file, self::CONFIG_EXTENSION);
            $data = require $file;

            if (!is_array($data)) {
                throw new ConfigException(
                    title: "Invalid Config File",
                    message: sprintf("Config file '%s' must return an array.", $file),
                    code: 500,
                    context: ['file' => $file]
                );
            }

            $this->configs[$key] = $data;
        }
    }

    private function loadTestConfiguration(string $testConfigDir): void
    {
        $pattern = rtrim($testConfigDir, '/\\') . DIRECTORY_SEPARATOR . '*' . self::CONFIG_TEST_EXTENSION;
        $files = glob($pattern) ?: [];

        foreach ($files as $file) {
            $key = basename($file, self::CONFIG_TEST_EXTENSION);
            $data = require $file;

            if (!is_array($data)) {
                throw new ConfigException(
                    title: "Invalid Test Config File",
                    message: sprintf("Test config file '%s' must return an array.", $file),
                    code: 500,
                    context: ['file' => $file]
                );
            }

            $this->configs[$key] = $this->deepMerge(
                $this->configs[$key] ?? [],
                $data
            );
        }
    }

    private function deepMerge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->deepMerge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    public function from(string $key): self
    {
        if (!isset($this->configs[$key])) {
            throw new ConfigException(
                title: "Config Not Found",
                message: sprintf("Config '%s' not found.", $key),
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
            throw new ConfigException(
                title: "Config Not Selected",
                message: "Call from() before calling get() or all().",
                code: 500
            );
        }
    }
}

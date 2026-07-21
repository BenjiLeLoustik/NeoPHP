<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Config;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Utils\Config\Exception\ConfigException;

class ConfigManager
{
    private const string CONFIG_EXTENSION = '.config.php';

    private const string CONFIG_TEST_EXTENSION = '.config.test.php';

    /** @var array<string, mixed> */
    private array $configs = [];

    /** @var array<string, mixed> */
    private array $current = [];

    /** @var array<string, string> */
    private array $files = [];

    private ?string $currentKey = null;

    private Container $container;

    private bool $hasSelected = false;

    /**
     * @throws ContainerException
     * @throws ConfigException
     */
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

    /**
     * @throws ConfigException
     */
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
            $this->files[$key] = $file;
        }
    }

    /**
     * @throws ConfigException
     */
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

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
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

    /**
     * @throws ConfigException
     */
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
        $this->currentKey = $key;
        $this->hasSelected = true;
        return $this;
    }

    /**
     * @throws ConfigException
     */
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

    /**
     * @return array<string, mixed>
     * @throws ConfigException
     */
    public function all(): array
    {
        $this->assertSelected();
        return $this->current;
    }

    /**
     * @throws ConfigException
     */
    public function set(string $path, mixed $value): self
    {
        $this->assertSelected();

        $segments = explode('.', $path);
        $lastKey = array_key_last($segments);
        $ref = &$this->current;

        foreach ($segments as $i => $segment) {
            if ($i === $lastKey) {
                $ref[$segment] = $value;
                break;
            }

            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }

            $ref = &$ref[$segment];
        }
        unset($ref);

        if ($this->currentKey !== null) {
            $this->configs[$this->currentKey] = $this->current;
        }

        return $this;
    }

    /**
     * @throws ConfigException
     */
    public function save(): self
    {
        $this->assertSelected();

        if ($this->container->has('testConfigsPath')) {
            throw new ConfigException(
                title: "Config Save Forbidden",
                message: "Saving configuration is forbidden while the test environment is active.",
                code: 500
            );
        }

        $file = $this->files[$this->currentKey] ?? null;

        if ($file === null) {
            throw new ConfigException(
                title: "Config File Not Found",
                message: sprintf("Config file '%s' not found.", (string)$this->currentKey),
                code: 500,
                context: ['key' => $this->currentKey]
            );
        }

        $content = "<?php\ndeclare(strict_types=1);\n\nreturn "
            . var_export($this->current, true)
            . ";\n";

        if (file_put_contents($file, $content, LOCK_EX) === false) {
            throw new ConfigException(
                title: "Config Write Failed",
                message: sprintf("Impossible d'écrire dans '%s'.", $file),
                code: 500,
                context: ['file' => $file]
            );
        }

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file, true);
        }

        return $this;
    }

    /**
     * @throws ConfigException
     */
    private function assertSelected(): void
    {
        if (!$this->hasSelected) {
            throw new ConfigException(
                title: "Config Not Selected",
                message: "Call from() before calling get() or all().",
                code: 500
            );
        }
    }
}

<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Config\Tests;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Utils\Config\Config;
use Neo\Core\Utils\Config\Exception\ConfigException;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private string $configsDir;
    private string $testConfigsDir;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . '/neo-config-test-' . uniqid();

        $this->configsDir = $base . '/configs';
        $this->testConfigsDir = $base . '/configs-test';

        mkdir($this->configsDir, 0777, true);
        mkdir($this->testConfigsDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDir(dirname($this->configsDir));
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->deleteDir($path) : unlink($path);
        }

        rmdir($dir);
    }

    /**
     * @param string $name
     * @param array<string, mixed> $data
     */
    private function writeConfig(string $name, array $data): void
    {
        file_put_contents(
            $this->configsDir . '/' . $name . '.config.php',
            '<?php return ' . var_export($data, true) . ';'
        );
    }

    /**
     * @param string $name
     * @param array<string, mixed> $data
     */
    private function writeTestConfig(string $name, array $data): void
    {
        file_put_contents(
            $this->testConfigsDir . '/' . $name . '.config.test.php',
            '<?php return ' . var_export($data, true) . ';'
        );
    }

    private function makeContainer(bool $withTestConfigs = false): Container
    {
        $container = new Container();
        $container->instance(Container::class, $container);
        $container->set('configsPath', $this->configsDir);

        if ($withTestConfigs) {
            $container->set('testConfigsPath', $this->testConfigsDir);
        }

        return $container;
    }

    /**
     * @throws ConfigException
     * @throws ContainerException
     */
    public function testFromThenGetReturnsNestedValue(): void
    {
        $this->writeConfig('app', ['general' => ['name' => 'NeoPHP']]);

        $config = new Config($this->makeContainer());

        self::assertSame('NeoPHP', $config->from('app')->get('general.name'));
    }

    /**
     * @throws ConfigException
     * @throws ContainerException
     */
    public function testFromThenAllReturnsWholeConfigArray(): void
    {
        $this->writeConfig('cache', ['driver' => 'files', 'ttl' => 3600]);

        $config = new Config($this->makeContainer());

        self::assertSame(['driver' => 'files', 'ttl' => 3600], $config->from('cache')->all());
    }

    /**
     * @throws ConfigException
     * @throws ContainerException
     */
    public function testGetReturnsDefaultWhenPathIsMissing(): void
    {
        $this->writeConfig('app', ['general' => ['name' => 'NeoPHP']]);

        $config = new Config($this->makeContainer());

        self::assertSame('fallback', $config->from('app')->get('general.missing', 'fallback'));
        self::assertNull($config->from('app')->get('unknown.path'));
    }

    /**
     * @throws ContainerException
     * @throws ConfigException
     */
    public function testFromThrowsWhenConfigKeyDoesNotExist(): void
    {
        $config = new Config($this->makeContainer());

        $this->expectException(ConfigException::class);

        $config->from('missing');
    }

    /**
     * @throws ContainerException
     * @throws ConfigException
     */
    public function testGetThrowsWhenFromWasNeverCalled(): void
    {
        $config = new Config($this->makeContainer());

        $this->expectException(ConfigException::class);

        $config->get('anything');
    }

    /**
     * @throws ContainerException
     */
    public function testConstructorThrowsWhenConfigFileDoesNotReturnAnArray(): void
    {
        file_put_contents($this->configsDir . '/broken.config.php', '<?php return "not-an-array";');

        $this->expectException(ConfigException::class);

        new Config($this->makeContainer());
    }

    /**
     * @throws ConfigException
     * @throws ContainerException
     */
    public function testEmptyArrayConfigIsTreatedAsNotSelected(): void
    {
        $this->writeConfig('empty', []);

        $config = new Config($this->makeContainer());
        $config->from('empty');

        $this->expectException(ConfigException::class);

        $config->all();
    }

    /**
     * @throws ConfigException
     * @throws ContainerException
     */
    public function testTestConfigsPathDeepMergesOverBaseConfig(): void
    {
        $this->writeConfig('app', [
            'general' => ['name' => 'NeoPHP', 'description' => 'Prod app'],
            'environment' => 'prod',
        ]);

        $this->writeTestConfig('app', [
            'environment' => 'test',
        ]);

        $config = new Config($this->makeContainer(withTestConfigs: true));

        self::assertSame(
            [
                'general' => ['name' => 'NeoPHP', 'description' => 'Prod app'],
                'environment' => 'test',
            ],
            $config->from('app')->all()
        );
    }

    /**
     * @throws ConfigException
     * @throws ContainerException
     */
    public function testTestConfigsPathIsIgnoredWhenDirectoryDoesNotExist(): void
    {
        $this->writeConfig('app', ['environment' => 'prod']);

        $container = $this->makeContainer();
        $container->set('testConfigsPath', $this->testConfigsDir . '/does-not-exist');

        $config = new Config($container);

        self::assertSame('prod', $config->from('app')->get('environment'));
    }
}
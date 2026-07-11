<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Logger\Tests;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Utils\Logger\Logger;
use PHPUnit\Framework\TestCase;

class LoggerTest extends TestCase
{
    private string $configsDir;
    private string $storageDir;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . '/neo-logger-test-' . uniqid();

        $this->configsDir = $base . '/configs';
        $this->storageDir = $base . '/storage';

        mkdir($this->configsDir, 0777, true);
        mkdir($this->storageDir, 0777, true);
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
     * @param array<string, mixed> $loggerConfig
     */
    private function makeContainer(array $loggerConfig): Container
    {
        $container = new Container();
        $container->instance(Container::class, $container);

        file_put_contents(
            $this->configsDir . '/logger.config.php',
            '<?php return ' . var_export($loggerConfig, true) . ';'
        );

        $container->set('configsPath', $this->configsDir);
        $container->set('storagePath', $this->storageDir);

        return $container;
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function defaultConfig(array $overrides = []): array
    {
        return array_replace_recursive([
            'enabled' => true,
            'min_level' => 'DEBUG',
            'channels' => [
                'app' => ['name' => 'app', 'extension' => 'log'],
            ],
            'rotation' => ['enabled' => false, 'type' => 'daily', 'max_file_size' => 5 * 1024 * 1024],
            'archive' => ['enabled' => false, 'extension' => 'zip'],
            'log_format' => '[{%level_name%}] {%message%} {%context%}',
        ], $overrides);
    }

    /**
     * @throws ContainerException
     */
    public function testLogDoesNothingWhenDisabled(): void
    {
        $logger = new Logger($this->makeContainer($this->defaultConfig(['enabled' => false])));

        $logger->info('should not be written');

        self::assertDirectoryDoesNotExist($this->storageDir . '/logs');
    }

    /**
     * @throws ContainerException
     */
    public function testInfoWritesFormattedEntryToLogFile(): void
    {
        $logger = new Logger($this->makeContainer($this->defaultConfig()));

        $logger->info('hello world', ['user' => 'neo']);

        $date = date('Y-m-d');
        $filePath = $this->storageDir . "/logs/app-{$date}.log";

        self::assertFileExists($filePath);
        $content = file_get_contents($filePath);

        self::assertStringContainsString('[INFO] hello world', $content);
        self::assertStringContainsString('"user":"neo"', $content);
    }

    /**
     * @throws ContainerException
     */
    public function testEntriesBelowMinLevelAreIgnored(): void
    {
        $logger = new Logger($this->makeContainer($this->defaultConfig(['min_level' => 'WARNING'])));

        $logger->info('ignored message');

        $date = date('Y-m-d');
        $filePath = $this->storageDir . "/logs/app-{$date}.log";

        self::assertFileDoesNotExist($filePath);
    }

    /**
     * @throws ContainerException
     */
    public function testWarningIsWrittenWhenMinLevelIsWarning(): void
    {
        $logger = new Logger($this->makeContainer($this->defaultConfig(['min_level' => 'WARNING'])));

        $logger->warning('disk almost full');

        $date = date('Y-m-d');
        $filePath = $this->storageDir . "/logs/app-{$date}.log";

        self::assertFileExists($filePath);
    }

    /**
     * @throws ContainerException
     */
    public function testLogThrowsOnUnknownLevel(): void
    {
        $logger = new Logger($this->makeContainer($this->defaultConfig()));

        $this->expectException(\InvalidArgumentException::class);

        $logger->log('TRACE', 'oops');
    }

    /**
     * @throws ContainerException
     */
    public function testChannelWritesToDistinctFileWithoutMutatingOriginal(): void
    {
        $logger = new Logger($this->makeContainer($this->defaultConfig([
            'channels' => [
                'app' => ['name' => 'app', 'extension' => 'log'],
                'mail' => ['name' => 'mail', 'extension' => 'log'],
            ],
        ])));

        $mailLogger = $logger->channel('mail');
        $mailLogger->info('mail sent');
        $logger->info('app event');

        $date = date('Y-m-d');

        self::assertFileExists($this->storageDir . "/logs/app-{$date}.log");
        self::assertFileExists($this->storageDir . "/logs/mail-{$date}.log");
    }

    /**
     * @throws ContainerException
     */
    public function testRotationBySizeRenamesOversizedFile(): void
    {
        $logger = new Logger($this->makeContainer($this->defaultConfig([
            'rotation' => ['enabled' => true, 'type' => 'size', 'max_file_size' => 10],
        ])));

        $logger->info('12345678901');
        $logger->info('second entry');

        $files = glob($this->storageDir . "/logs/app.log*");

        self::assertGreaterThanOrEqual(2, count($files), "Fichiers trouvés : " . implode(', ', $files));
    }

    /**
     * @throws ContainerException
     */
    public function testArchiveMovesStaleLogFilesIntoZip(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('ext-zip is not available.');
        }

        $logsDir = $this->storageDir . '/logs';
        mkdir($logsDir, 0777, true);
        file_put_contents($logsDir . '/app-2020-01-01.log', '[INFO] old entry');

        $logger = new Logger($this->makeContainer($this->defaultConfig([
            'archive' => ['enabled' => true, 'extension' => 'zip'],
        ])));

        $logger->info('fresh entry');

        self::assertFileDoesNotExist($logsDir . '/app-2020-01-01.log');

        $archived = glob($this->storageDir . '/logs/archives/2020/01/*.zip');

        self::assertCount(1, $archived);
        self::assertSame('2020-01-01.zip', basename($archived[0]));
    }

    /**
     * @throws ContainerException
     */
    public function testArchiveGroupsAllChannelsOfSameDateIntoSingleZip(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('ext-zip is not available.');
        }

        $logsDir = $this->storageDir . '/logs';
        mkdir($logsDir, 0777, true);
        file_put_contents($logsDir . '/app-2020-01-01.log', '[INFO] app entry');
        file_put_contents($logsDir . '/mail-2020-01-01.log', '[INFO] mail entry');

        $logger = new Logger($this->makeContainer($this->defaultConfig([
            'channels' => [
                'app' => ['name' => 'app', 'extension' => 'log'],
                'mail' => ['name' => 'mail', 'extension' => 'log'],
            ],
            'archive' => ['enabled' => true, 'extension' => 'zip'],
        ])));

        $logger->info('fresh entry');

        self::assertFileDoesNotExist($logsDir . '/app-2020-01-01.log');
        self::assertFileDoesNotExist($logsDir . '/mail-2020-01-01.log');

        $zipPath = $this->storageDir . '/logs/archives/2020/01/2020-01-01.zip';
        self::assertFileExists($zipPath);

        $zip = new \ZipArchive();
        $zip->open($zipPath);

        self::assertSame(2, $zip->numFiles);
        self::assertNotFalse($zip->locateName('app-2020-01-01.log'));
        self::assertNotFalse($zip->locateName('mail-2020-01-01.log'));

        $zip->close();
    }
}
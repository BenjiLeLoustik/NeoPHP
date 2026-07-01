<?php
declare(strict_types=1);

namespace Neo\Core\Translation\Tests\Writer;

use Neo\Core\Translation\Exception\TranslationException;
use Neo\Core\Translation\Loader\TranslationLoader;
use Neo\Core\Translation\TranslationRegistry;
use Neo\Core\Translation\Writer\TranslationWriter;
use PHPUnit\Framework\TestCase;

class TranslationWriterTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->resetRegistry();

        $this->path = sys_get_temp_dir() . '/neo-translation-writer-' . uniqid();
        mkdir($this->path, 0777, true);
        TranslationRegistry::registerPath($this->path);
    }

    private function resetRegistry(): void
    {
        $ref = new \ReflectionProperty(TranslationRegistry::class, 'paths');
        $ref->setValue(null, []);
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->path);
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
            $itemPath = $dir . '/' . $item;
            is_dir($itemPath) ? $this->deleteDir($itemPath) : unlink($itemPath);
        }

        rmdir($dir);
    }

    /**
     * @throws TranslationException
     */
    public function testEnsureCreatesFileAndWritesKeyWhenMissing(): void
    {
        $loader = new TranslationLoader();
        $writer = new TranslationWriter($loader);

        $writer->ensure('fr', 'Bonjour');

        $filePath = $this->path . '/fr/common.php';
        self::assertFileExists($filePath);

        $translations = require $filePath;
        self::assertSame(['Bonjour' => 'Bonjour'], $translations);
    }

    /**
     * @throws TranslationException
     */
    public function testEnsureDoesNotOverwriteExistingKey(): void
    {
        mkdir($this->path . '/fr', 0777, true);
        file_put_contents(
            $this->path . '/fr/common.php',
            "<?php return ['Bonjour' => 'Salut'];"
        );

        $loader = new TranslationLoader();
        $writer = new TranslationWriter($loader);

        $writer->ensure('fr', 'Bonjour');

        $translations = require $this->path . '/fr/common.php';
        self::assertSame(['Bonjour' => 'Salut'], $translations);
    }

    /**
     * @throws TranslationException
     */
    public function testEnsureInvalidatesLoaderCacheAfterWriting(): void
    {
        $loader = new TranslationLoader();
        $writer = new TranslationWriter($loader);

        self::assertSame([], $loader->load('fr'));

        $writer->ensure('fr', 'Bonjour');

        self::assertSame(['Bonjour' => 'Bonjour'], $loader->load('fr'));
    }

    public function testEnsureThrowsWhenExistingFileDoesNotReturnAnArray(): void
    {
        mkdir($this->path . '/fr', 0777, true);
        file_put_contents($this->path . '/fr/common.php', "<?php return 'not-an-array';");

        $loader = new TranslationLoader();
        $writer = new TranslationWriter($loader);

        $this->expectException(TranslationException::class);

        $writer->ensure('fr', 'Bonjour');
    }
}
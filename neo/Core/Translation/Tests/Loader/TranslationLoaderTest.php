<?php
declare(strict_types=1);

namespace Neo\Core\Translation\Tests\Loader;

use Neo\Core\Translation\Exception\TranslationException;
use Neo\Core\Translation\Loader\TranslationLoader;
use Neo\Core\Translation\TranslationRegistry;
use PHPUnit\Framework\TestCase;

class TranslationLoaderTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->resetRegistry();

        $this->path = sys_get_temp_dir() . '/neo-translation-loader-' . uniqid();
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

    private function writeMessages(string $locale, string $file, array $content): void
    {
        $dir = $this->path . '/' . $locale;
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents(
            $dir . '/' . $file . '.php',
            '<?php return ' . var_export($content, true) . ';'
        );
    }

    /**
     * @throws TranslationException
     */
    public function testLoadReturnsTranslationsFromFile(): void
    {
        $this->writeMessages('fr', 'messages', [
            'hello' => 'Bonjour',
            'nested' => ['key' => 'Valeur'],
        ]);

        $loader = new TranslationLoader();

        self::assertSame(
            ['hello' => 'Bonjour', 'nested' => ['key' => 'Valeur']],
            $loader->load('fr', 'messages')
        );
    }

    /**
     * @throws TranslationException
     */
    public function testLoadReturnsEmptyArrayWhenFileIsMissing(): void
    {
        $loader = new TranslationLoader();

        self::assertSame([], $loader->load('fr', 'unknown'));
    }

    /**
     * @throws TranslationException
     */
    public function testLoadCachesResultUntilInvalidated(): void
    {
        $this->writeMessages('fr', 'messages', ['hello' => 'Bonjour']);

        $loader = new TranslationLoader();
        $first = $loader->load('fr', 'messages');

        $this->writeMessages('fr', 'messages', ['hello' => 'Salut']);
        $second = $loader->load('fr', 'messages');

        self::assertSame($first, $second);

        $loader->invalidate('fr', 'messages');
        $third = $loader->load('fr', 'messages');

        self::assertSame(['hello' => 'Salut'], $third);
    }

    /**
     * @throws TranslationException
     */
    public function testLoadMergesTranslationsFromMultipleRegisteredPaths(): void
    {
        $secondPath = sys_get_temp_dir() . '/neo-translation-loader-' . uniqid();
        mkdir($secondPath . '/fr', 0777, true);
        file_put_contents(
            $secondPath . '/fr/messages.php',
            "<?php return ['extra' => 'Supplementaire'];"
        );
        TranslationRegistry::registerPath($secondPath);

        $this->writeMessages('fr', 'messages', ['hello' => 'Bonjour']);

        $loader = new TranslationLoader();

        self::assertSame(
            ['hello' => 'Bonjour', 'extra' => 'Supplementaire'],
            $loader->load('fr', 'messages')
        );

        $this->deleteDir($secondPath);
    }

    public function testLoadThrowsWhenFileDoesNotReturnAnArray(): void
    {
        $dir = $this->path . '/fr';
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/broken.php', "<?php return 'not-an-array';");

        $loader = new TranslationLoader();

        $this->expectException(TranslationException::class);

        $loader->load('fr', 'broken');
    }
}
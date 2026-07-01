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

    /**
     * @param array<string, string> $content
     */
    private function writeLocale(string $locale, array $content, string $domain = 'common'): void
    {
        $dir = $this->path . '/' . $locale;

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents(
            $dir . '/' . $domain . '.php',
            '<?php return ' . var_export($content, true) . ';'
        );
    }

    /**
     * @throws TranslationException
     */
    public function testLoadReturnsTranslationsFromFile(): void
    {
        $this->writeLocale('fr', [
            'Bonjour' => 'Bonjour',
            'Au revoir' => 'Au revoir',
        ]);

        $loader = new TranslationLoader();

        self::assertSame(
            ['Bonjour' => 'Bonjour', 'Au revoir' => 'Au revoir'],
            $loader->load('fr')
        );
    }

    /**
     * @throws TranslationException
     */
    public function testLoadReturnsEmptyArrayWhenFileIsMissing(): void
    {
        $loader = new TranslationLoader();

        self::assertSame([], $loader->load('en'));
    }

    /**
     * @throws TranslationException
     */
    public function testLoadCachesResultUntilInvalidated(): void
    {
        $this->writeLocale('fr', ['Bonjour' => 'Bonjour']);

        $loader = new TranslationLoader();
        $first  = $loader->load('fr');

        $this->writeLocale('fr', ['Bonjour' => 'Salut']);
        $second = $loader->load('fr');

        self::assertSame($first, $second);

        $loader->invalidate('fr');
        $third = $loader->load('fr');

        self::assertSame(['Bonjour' => 'Salut'], $third);
    }

    /**
     * @throws TranslationException
     */
    public function testLoadMergesTranslationsFromMultipleRegisteredPaths(): void
    {
        $secondPath = sys_get_temp_dir() . '/neo-translation-loader-' . uniqid();
        mkdir($secondPath . '/fr', 0777, true);
        file_put_contents(
            $secondPath . '/fr/common.php',
            "<?php return ['Extra' => 'Supplementaire'];"
        );
        TranslationRegistry::registerPath($secondPath);

        $this->writeLocale('fr', ['Bonjour' => 'Bonjour']);

        $loader = new TranslationLoader();

        self::assertSame(
            ['Bonjour' => 'Bonjour', 'Extra' => 'Supplementaire'],
            $loader->load('fr')
        );

        $this->deleteDir($secondPath);
    }

    public function testLoadThrowsWhenFileDoesNotReturnAnArray(): void
    {
        mkdir($this->path . '/fr', 0777, true);
        file_put_contents($this->path . '/fr/common.php', "<?php return 'not-an-array';");

        $loader = new TranslationLoader();

        $this->expectException(TranslationException::class);

        $loader->load('fr');
    }
}
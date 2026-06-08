<?php
declare(strict_types=1);

namespace Neo\Core\Http\Tests\File;

use Neo\Core\DI\Container;
use Neo\Core\Http\File\Exception\UploaderException;
use Neo\Core\Http\File\UploadedFile;
use Neo\Core\Http\File\Uploader;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UploaderTest extends TestCase
{
    private string $tmpDir;
    private string $assetsDir;

    /** @var Container&MockObject */
    private Container $container;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/uploader_test_' . uniqid('', true);
        $this->assetsDir = $this->tmpDir . '/assets';

        mkdir($this->tmpDir, 0775, true);
        mkdir($this->assetsDir, 0775, true);

        $this->container = $this->createMock(Container::class);
        $this->container
            ->method('get')
            ->with('assetsPath')
            ->willReturn($this->assetsDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . '/' . $item;
            is_dir($full) ? $this->removeDirectory($full) : unlink($full);
        }

        rmdir($path);
    }

    private function makeUploadedFile(array $overrides = []): UploadedFile
    {
        $tmp = tempnam($this->tmpDir, 'upl');
        if ($tmp === false) {
            throw new \RuntimeException('Cannot create temp file.');
        }
        file_put_contents($tmp, 'fake content');

        $data = array_merge([
            'name' => 'document.pdf',
            'tmp_name' => $tmp,
            'size' => 12,
            'error' => UPLOAD_ERR_OK,
        ], $overrides);

        return new UploadedFile($data);
    }

    public function testUploadThrowsWhenFileIsInvalid(): void
    {
        $uploader = new Uploader($this->container);
        $file = $this->makeUploadedFile(['error' => UPLOAD_ERR_INI_SIZE]);

        $this->expectException(UploaderException::class);
        $this->expectExceptionMessage('Invalid uploaded file.');

        $uploader->upload($file, 'test', [], 'uploads');
    }

    public function testUploadThrowsWhenFileHasPartialError(): void
    {
        $uploader = new Uploader($this->container);
        $file = $this->makeUploadedFile(['error' => UPLOAD_ERR_PARTIAL]);

        $this->expectException(UploaderException::class);

        $uploader->upload($file, 'test', [], 'uploads');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('forbiddenExtensionsProvider')]
    public function testUploadThrowsForForbiddenExtension(string $filename): void
    {
        $uploader = new Uploader($this->container);
        $file = $this->makeUploadedFile(['name' => $filename]);

        $this->expectException(UploaderException::class);
        $this->expectExceptionMessage('Forbidden file type');

        $uploader->upload($file, 'test', [], 'uploads');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function forbiddenExtensionsProvider(): array
    {
        return [
            'php' => ['script.php'],
            'phtml' => ['template.phtml'],
            'exe' => ['program.exe'],
            'sh' => ['run.sh'],
            'js' => ['payload.js'],
        ];
    }

    public function testForbiddenExtensionIsCaseInsensitive(): void
    {
        $uploader = new Uploader($this->container);
        $file = $this->makeUploadedFile(['name' => 'shell.PHP']);

        $this->expectException(UploaderException::class);
        $this->expectExceptionMessage('Forbidden file type');

        $uploader->upload($file, 'test', [], 'uploads');
    }

    public function testUploadThrowsWhenExtensionNotInAllowedList(): void
    {
        $uploader = new Uploader($this->container);
        $file = $this->makeUploadedFile(['name' => 'archive.zip']);

        $this->expectException(UploaderException::class);
        $this->expectExceptionMessage('Extension .zip not allowed.');

        $uploader->upload($file, 'test', ['pdf', 'png'], 'uploads');
    }

    public function testUploadThrowsWhenExtensionNotInAllowedListVariant(): void
    {
        $uploader = new Uploader($this->container);
        $file = $this->makeUploadedFile(['name' => 'photo.bmp']);

        $this->expectException(UploaderException::class);
        $this->expectExceptionMessage('Extension .bmp not allowed.');

        $uploader->upload($file, 'photo', ['jpg', 'png', 'gif'], 'images');
    }

    public function testUploadWithEmptyAllowedExtensionsAcceptsAnyNonForbiddenType(): void
    {
        $uploader = new Uploader($this->container);
        $file = $this->makeUploadedFile(['name' => 'data.csv']);

        $this->expectException(UploaderException::class);
        $this->expectExceptionMessage('Upload failed.');

        $uploader->upload($file, 'data', [], 'exports');
    }

    public function testUploadCreatesDestinationDirectoryIfMissing(): void
    {
        $uploader = new Uploader($this->container);
        $newDir = $this->assetsDir . '/new_subdir';
        $file = $this->makeUploadedFile(['name' => 'image.png']);

        self::assertDirectoryDoesNotExist($newDir);

        try {
            $uploader->upload($file, 'image', ['png'], 'new_subdir');
        } catch (UploaderException $e) {}

        self::assertDirectoryExists($newDir);
    }

    public function testUploadUsesExistingDirectoryWithoutError(): void
    {
        $uploader = new Uploader($this->container);
        $existingDir = $this->assetsDir . '/photos';
        mkdir($existingDir, 0775, true);

        $file = $this->makeUploadedFile(['name' => 'portrait.png']);

        try {
            $uploader->upload($file, 'portrait', ['png'], 'photos');
        } catch (UploaderException $e) {
            self::assertSame('Upload failed.', $e->getMessage());
        }

        self::assertDirectoryExists($existingDir);
    }

    public function testAssetsPathTrailingSlashIsNormalized(): void
    {
        $containerWithSlash = $this->createMock(Container::class);
        $containerWithSlash
            ->method('get')
            ->with('assetsPath')
            ->willReturn($this->assetsDir . '/');

        $uploader = new Uploader($containerWithSlash);
        $file = $this->makeUploadedFile(['name' => 'file.txt']);

        try {
            $uploader->upload($file, 'file', ['txt'], 'docs');
        } catch (UploaderException $e) {
            $expectedDir = $this->assetsDir . '/docs';
            self::assertDirectoryExists($expectedDir);
        }
    }

    public function testUploadFailedExceptionMessageIsCorrect(): void
    {
        $uploader = new Uploader($this->container);
        $file = $this->makeUploadedFile(['name' => 'doc.pdf']);

        try {
            $uploader->upload($file, 'my-doc', ['pdf'], 'documents');
            self::fail('UploaderException expected.');
        } catch (UploaderException $e) {
            self::assertSame('Upload failed.', $e->getMessage());
        }
    }

    public function testUploaderExceptionExtendsFrameworkException(): void
    {
        $uploader = new Uploader($this->container);
        $file = $this->makeUploadedFile(['error' => UPLOAD_ERR_NO_FILE]);

        try {
            $uploader->upload($file, 'file', [], 'uploads');
            self::fail('UploaderException expected.');
        } catch (UploaderException $e) {
            self::assertInstanceOf(\Neo\Core\Error\Exception\FrameworkException::class, $e);
        }
    }
}
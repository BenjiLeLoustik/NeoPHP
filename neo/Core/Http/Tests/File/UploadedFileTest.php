<?php
declare(strict_types=1);

namespace Neo\Core\Http\Tests\File;

use Neo\Core\Http\File\UploadedFile;
use PHPUnit\Framework\TestCase;

class UploadedFileTest extends TestCase
{
    /**
     * @param array{name: string, tmp_name: string, size: int, error: int} $data
     */
    private function makeFile(array $data): UploadedFile
    {
        return new UploadedFile($data);
    }

    private function validFileData(
        string $name = 'photo.jpg',
        string $tmpName = '/tmp/phpXXXXXX',
        int $size = 204800,
        int $error = UPLOAD_ERR_OK
    ): array {
        return [
            'name' => $name,
            'tmp_name' => $tmpName,
            'size' => $size,
            'error' => $error,
        ];
    }

    public function testGetOriginalNameReturnsFileName(): void
    {
        $file = $this->makeFile($this->validFileData(name: 'avatar.png'));

        self::assertSame('avatar.png', $file->getOriginalName());
    }

    public function testGetOriginalNameWithDotsInName(): void
    {
        $file = $this->makeFile($this->validFileData(name: 'my.report.v2.pdf'));

        self::assertSame('my.report.v2.pdf', $file->getOriginalName());
    }

    public function testGetOriginalNameWithSpaces(): void
    {
        $file = $this->makeFile($this->validFileData(name: 'my document.docx'));

        self::assertSame('my document.docx', $file->getOriginalName());
    }

    public function testGetTempPathReturnsPath(): void
    {
        $file = $this->makeFile($this->validFileData(tmpName: '/tmp/phpABCDEF'));

        self::assertSame('/tmp/phpABCDEF', $file->getTempPath());
    }

    public function testGetTempPathWithWindowsStyle(): void
    {
        $file = $this->makeFile($this->validFileData(tmpName: 'C:\\Windows\\Temp\\phpXXX'));

        self::assertSame('C:\\Windows\\Temp\\phpXXX', $file->getTempPath());
    }

    public function testGetSizeReturnsInteger(): void
    {
        $file = $this->makeFile($this->validFileData(size: 1024));

        self::assertSame(1024, $file->getSize());
    }

    public function testGetSizeZero(): void
    {
        $file = $this->makeFile($this->validFileData(size: 0));

        self::assertSame(0, $file->getSize());
    }

    public function testGetSizeLargeFile(): void
    {
        $file = $this->makeFile($this->validFileData(size: 10_485_760)); // 10 Mo

        self::assertSame(10_485_760, $file->getSize());
    }

    public function testGetErrorReturnsUploadErrOk(): void
    {
        $file = $this->makeFile($this->validFileData(error: UPLOAD_ERR_OK));

        self::assertSame(UPLOAD_ERR_OK, $file->getError());
    }

    public function testGetErrorReturnsUploadErrIniSize(): void
    {
        $file = $this->makeFile($this->validFileData(error: UPLOAD_ERR_INI_SIZE));

        self::assertSame(UPLOAD_ERR_INI_SIZE, $file->getError());
    }

    public function testGetErrorReturnsUploadErrNoFile(): void
    {
        $file = $this->makeFile($this->validFileData(error: UPLOAD_ERR_NO_FILE));

        self::assertSame(UPLOAD_ERR_NO_FILE, $file->getError());
    }

    public function testGetErrorReturnsUploadErrPartial(): void
    {
        $file = $this->makeFile($this->validFileData(error: UPLOAD_ERR_PARTIAL));

        self::assertSame(UPLOAD_ERR_PARTIAL, $file->getError());
    }

    public function testIsValidReturnsTrueWhenErrorIsOk(): void
    {
        $file = $this->makeFile($this->validFileData(error: UPLOAD_ERR_OK));

        self::assertTrue($file->isValid());
    }

    public function testIsValidReturnsFalseWhenErrorIsIniSize(): void
    {
        $file = $this->makeFile($this->validFileData(error: UPLOAD_ERR_INI_SIZE));

        self::assertFalse($file->isValid());
    }

    public function testIsValidReturnsFalseWhenErrorIsFormSize(): void
    {
        $file = $this->makeFile($this->validFileData(error: UPLOAD_ERR_FORM_SIZE));

        self::assertFalse($file->isValid());
    }

    public function testIsValidReturnsFalseWhenErrorIsPartial(): void
    {
        $file = $this->makeFile($this->validFileData(error: UPLOAD_ERR_PARTIAL));

        self::assertFalse($file->isValid());
    }

    public function testIsValidReturnsFalseWhenErrorIsNoFile(): void
    {
        $file = $this->makeFile($this->validFileData(error: UPLOAD_ERR_NO_FILE));

        self::assertFalse($file->isValid());
    }

    public function testIsValidReturnsFalseWhenErrorIsNoTmpDir(): void
    {
        $file = $this->makeFile($this->validFileData(error: UPLOAD_ERR_NO_TMP_DIR));

        self::assertFalse($file->isValid());
    }

    public function testIsValidReturnsFalseWhenErrorIsCantWrite(): void
    {
        $file = $this->makeFile($this->validFileData(error: UPLOAD_ERR_CANT_WRITE));

        self::assertFalse($file->isValid());
    }

    public function testIsValidReturnsFalseWhenErrorIsExtension(): void
    {
        $file = $this->makeFile($this->validFileData(error: UPLOAD_ERR_EXTENSION));

        self::assertFalse($file->isValid());
    }
}
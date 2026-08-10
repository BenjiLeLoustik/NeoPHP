<?php
declare(strict_types=1);

namespace Neo\Core\Http\File\Model;

use Neo\Core\Http\File\Exception\UploadException;

class UploadedFile
{
    private const int DEFAULT_MAX_SIZE = 8 * 1024 * 1024;

    /** @var array<int, string> */
    private const array UPLOAD_ERRORS = [
        UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
        UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.',
        UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
    ];

    /**
     * @var array{
     *     name: string,
     *     tmp_name: string,
     *     size: int,
     *     error: int
     * }
     */
    private array $file;

    /**
     * @param array{
     *     name: string,
     *     tmp_name: string,
     *     size: int,
     *     error: int
     * } $file
     */
    public function __construct(array $file)
    {
        $this->file = $file;
    }

    public function getOriginalName(): string
    {
        return $this->file['name'];
    }

    public function getSafeName(): string
    {
        $name = pathinfo($this->file['name'], PATHINFO_FILENAME);
        $ext = pathinfo($this->file['name'], PATHINFO_EXTENSION);

        $name = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $name) ?? 'file';
        $ext = preg_replace('/[^a-zA-Z0-9]/', '', $ext) ?? '';

        $name = trim($name, '_');
        $name = $name !== '' ? $name : 'file';

        return $ext !== '' ? "$name.$ext" : $name;
    }

    public function getTempPath(): string
    {
        return $this->file['tmp_name'];
    }

    public function getSize(): int
    {
        return $this->file['size'];
    }

    public function getError(): int
    {
        return $this->file['error'];
    }

    public function isValid(): bool
    {
        return $this->file['error'] === UPLOAD_ERR_OK;
    }

    /**
     * @throws UploadException
     */
    public function assertValid(): void
    {
        if ($this->file['error'] !== UPLOAD_ERR_OK) {
            throw new UploadException(
                title: 'Upload Error',
                message: self::UPLOAD_ERRORS[$this->file['error']] ?? 'Unknown upload error.',
                code: 400
            );
        }
    }

    /**
     * @throws UploadException
     */
    public function assertMaxSize(int $maxBytes = self::DEFAULT_MAX_SIZE): void
    {
        if ($this->file['size'] > $maxBytes) {
            throw new UploadException(
                title: 'Upload Error',
                message: sprintf('File exceeds maximum allowed size of %d bytes.', $maxBytes),
                code: 413
            );
        }
    }

    /**
     * @param array<int, string> $allowedMimes
     * @throws UploadException
     */
    public function assertMimeType(array $allowedMimes): void
    {
        $detected = mime_content_type($this->file['tmp_name']);

        if ($detected === false || !in_array($detected, $allowedMimes, true)) {
            throw new UploadException(
                title: 'Upload Error',
                message: sprintf(
                    "File type '%s' is not allowed. Allowed types: %s.",
                    $detected ?: 'unknown',
                    implode(', ', $allowedMimes)
                ),
                code: 415
            );
        }
    }

    /**
     * @param array<int, string> $allowedMimes
     * @throws UploadException
     */
    public function validate(array $allowedMimes = [], int $maxBytes = self::DEFAULT_MAX_SIZE): void
    {
        $this->assertValid();
        $this->assertMaxSize($maxBytes);

        if (!empty($allowedMimes)) {
            $this->assertMimeType($allowedMimes);
        }
    }

    /**
     * @throws UploadException
     */
    public function moveTo(string $destination): void
    {
        if (!$this->isValid()) {
            throw new UploadException(
                title: 'Upload Error',
                message: 'Cannot move an invalid uploaded file.',
                code: 400
            );
        }

        $dir = dirname($destination);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new UploadException(
                title: 'Upload Error',
                message: sprintf("Failed to create directory '%s'.", $dir),
                code: 500
            );
        }

        if (!move_uploaded_file($this->file['tmp_name'], $destination)) {
            throw new UploadException(
                title: 'Upload Error',
                message: 'Failed to move uploaded file to destination.',
                code: 500
            );
        }
    }

    public function getMimeType(): ?string
    {
        $mime = mime_content_type($this->file['tmp_name']);
        return $mime !== false ? $mime : null;
    }

    public function getExtension(): string
    {
        return $this->file['name']
                |> (fn (string $n): string => pathinfo($n, PATHINFO_EXTENSION))
                |> strtolower(...);
    }
}
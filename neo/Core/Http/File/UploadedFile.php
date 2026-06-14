<?php
declare(strict_types=1);

namespace Neo\Core\Http\File;

class UploadedFile
{
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
}
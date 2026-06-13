<?php
declare(strict_types=1);

namespace Neo\Core\Http\File;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Http\File\Exception\UploaderException;

class Uploader
{
    private string $assetsPath;

    /**
     * @throws ContainerException
     */
    public function __construct(Container $container)
    {
        $this->assetsPath = rtrim($container->get('assetsPath'), '/');
    }

    /**
     * @throws UploaderException
     */
    public function upload(
        UploadedFile $file,
        string $name,
        array $allowedExtensions,
        string $directory
    ): string {
        if (!$file->isValid()) {
            throw new UploaderException(
                title: 'Invalid File',
                message: 'Invalid uploaded file.',
                code: 500,
            );
        }

        $extension = strtolower(pathinfo($file->getOriginalName(), PATHINFO_EXTENSION));

        $forbidden = ['php', 'phtml', 'exe', 'sh', 'js'];
        if (in_array($extension, $forbidden, true)) {
            throw new UploaderException(
                title: 'Forbidden File Type',
                message: sprintf('Forbidden file type : %s.', $extension),
                code: 500,
            );
        }

        if (!empty($allowedExtensions) && !in_array($extension, $allowedExtensions, true)) {
            throw new UploaderException(
                title: 'Extension Not Allowed',
                message: sprintf('Extension .%s not allowed.', $extension),
                code: 500,
            );
        }

        $destinationDir = $this->assetsPath . '/' . trim($directory, '/');

        if (!is_dir($destinationDir)) {
            mkdir($destinationDir, 0775, true);
        }

        $finalName = $name . '.' . $extension;
        $destination = $destinationDir . '/' . $finalName;

        if (file_exists($destination)) {
            $finalName = $name . '_' . time() . '.' . $extension;
            $destination = $destinationDir . '/' . $finalName;
        }

        if (!move_uploaded_file($file->getTempPath(), $destination)) {
            throw new UploaderException(
                title: 'Upload Failed',
                message: 'Upload failed.',
                code: 500,
                context: []
            );
        }

        return $finalName;
    }
}
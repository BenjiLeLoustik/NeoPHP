<?php
declare(strict_types=1);

namespace Neo\Core\Http\File;

use Neo\Core\DI\Container;
use RuntimeException;

class Uploader
{
    private string $assetsPath;

    public function __construct(Container $container)
    {
        $this->assetsPath = rtrim($container->get('assetsPath'), '/');
    }

    public function upload(UploadedFile $file, string $name, array $allowedExtensions, string $directory): string
    {
        if (!$file->isValid()) {
            throw new RuntimeException('File is not valid.');
        }

        $extension = strtolower(pathinfo($file->getOriginalName(), PATHINFO_EXTENSION));
        $forbidden = ['php', 'phtml', 'exe', 'sh', 'js'];
        if (in_array($extension, $forbidden, true)) {
            throw new RuntimeException('Invalid file extension: ' . $extension);
        }

        if (!empty($allowedExtensions) && !in_array($extension, $allowedExtensions, true)) {
            throw new RuntimeException("Extension $extension is not allowed.");
        }

        $fileName = $name . '.' . $extension;
        $destinationDir = $this->assetsPath . '/' . trim($directory, '/');

        if (!is_dir($destinationDir)) {
            mkdir($destinationDir, 0755, true);
        }

        $destination = $destinationDir . '/' . $fileName;
        if (file_exists($destination)) {
            $finalName = $name . '_' . time() . '.' . $extension;
            $destination = $destinationDir . '/' . $finalName;
        }

        if (!move_uploaded_file($file->getTempPath(), $destination)) {
            throw new RuntimeException('Failed to upload file: ' . $destination);
        }

        return $finalName;
    }
}
<?php

namespace Neo\Core\Package;

class PackageManager
{
    public static function copyConfigDefaults(
        string $configPath,
        string $appPath,
        string $packageName
    ): void {
        $targetDir = rtrim($appPath, '/\\') . '/Config/Packages/' . $packageName;

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        foreach (glob(rtrim($configPath, '/\\') . '/*.config.php') ?: [] as $file) {
            $target = $targetDir . '/' . basename($file);

            if (!file_exists($target)) {
                copy($file, $target);
            }
        }
    }
}
<?php
declare(strict_types=1);

namespace Neo\Core\Asset;

use Neo\Core\Asset\Compiler\CssCompiler;
use Neo\Core\Asset\Compiler\JsCompiler;
use Neo\Core\Asset\Compiler\LessCompiler;
use Neo\Core\Asset\Exception\AssetException;
use Neo\Core\DI\Container;
use Neo\Core\Utils\Config\Config;
use Neo\Core\View\View;

class AssetHandler
{
    private Container $container;
    private string $sourcePath;
    private string $currentApplication;
    private string $buildPath;
    private string $manifestPath;
    private array $manifest = [];
    private string $env;
    private string $publicPath;

    public function __construct(Container $container)
    {
        $this->container = $container;

        $config = $this->container->get(Config::class)->from('app');
        $this->env = $config->get('environment', 'prod');

        $this->currentApplication = $this->container->get('application');
        $this->sourcePath = $this->container->get('assetsPath');
        $this->publicPath = $this->container->get('publicPath');
        $this->buildPath = $this->container->get('buildsPath') . $this->currentApplication . '/assets/';
        $this->manifestPath = $this->container->get('buildsPath') . $this->currentApplication . '/' . $this->container->get('manifestFilename');

        $this->loadManifest();

        $this->container->get(View::class)->registerTwigFunction('asset', function (string $path) {
            return $this->getAssetPath($path);
        });
    }

    private function loadManifest(): void
    {
        if (!file_exists($this->manifestPath)) {
            return;
        }

        $json = file_get_contents($this->manifestPath);

        if ($json === false) {
            throw new AssetException(
                title: 'Asset Manifest Unreadable',
                message: sprintf("Unable to read manifest '%s'.", $this->manifestPath),
                code: 500
            );
        }

        $this->manifest = json_decode($json, true) ?: [];
    }

    private function saveManifest(): void
    {
        $dir = dirname($this->manifestPath);

        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new AssetException(
                title: 'Asset Manifest Directory Error',
                message: sprintf("Unable to create manifest directory '%s'.", $dir),
                code: 500
            );
        }

        $result = file_put_contents(
            $this->manifestPath,
            json_encode($this->manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        if ($result === false) {
            throw new AssetException(
                title: 'Asset Manifest Write Error',
                message: sprintf("Unable to write manifest '%s'.", $this->manifestPath),
                code: 500
            );
        }
    }

    public function getAssetPath(string $path): string
    {
        if ($this->env === 'prod') {
            if (isset($this->manifest[$path])) {
                return $this->manifest[$path];
            }

            return '/builds/' . $this->currentApplication . '/assets/' . ltrim($path, '/');
        }

        $sourceFile = $this->sourcePath . $path;

        if (!file_exists($sourceFile)) {
            throw new AssetException(
                title: 'Asset Source Missing',
                message: sprintf("The source file '%s' could not be found.", $path),
                code: 404
            );
        }

        $currentHash = substr(md5_file($sourceFile), 0, 8);

        if (isset($this->manifest[$path]) && str_contains($this->manifest[$path], $currentHash)) {
            return $this->manifest[$path];
        }

        return $this->compile($path);
    }

    private function compile(string $path): string
    {
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $targetExt = $ext === 'less' ? 'css' : $ext;

        $compiler = match($ext) {
            'css' => new CssCompiler(),
            'js' => new JsCompiler(),
            'less' => new LessCompiler(),
            default => null
        };

        $sourceFile = $this->sourcePath . $path;
        $hash = substr(md5_file($sourceFile), 0, 8);
        $targetDir = $this->buildPath . dirname($path);

        if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
            throw new AssetException(
                title: 'Asset Build Directory Error',
                message: sprintf("Unable to create build directory '%s'.", $targetDir),
                code: 500
            );
        }

        $filename = basename($path, '.' . $ext);
        $minSuffix = in_array($targetExt, ['css', 'js']) ? '.min' : '';
        $targetFile = "{$targetDir}/{$filename}-{$hash}{$minSuffix}.{$targetExt}";

        if (isset($this->manifest[$path])) {
            $oldRelative = $this->manifest[$path];
            $oldReal = $this->publicPath . '/' . ltrim($oldRelative, '/');

            if (file_exists($oldReal) && is_file($oldReal)) {
                unlink($oldReal);
            }
        }

        if ($compiler) {
            try {
                $compiler->compile($sourceFile, $targetFile);
            } catch (\Throwable $e) {
                throw new AssetException(
                    title: 'Asset Compilation Error',
                    message: sprintf("Error compiling '%s': %s.", $path, $e->getMessage()),
                    code: 500,
                    previous: $e
                );
            }
        } else {
            if (!copy($sourceFile, $targetFile)) {
                throw new AssetException(
                    title: 'Asset Copy Error',
                    message: sprintf("Failed to copy '%s' to '%s'.", $sourceFile, $targetFile),
                    code: 500
                );
            }
        }

        $relativePath = '/builds/'
            . $this->currentApplication
            . '/assets/'
            . ltrim(str_replace($this->buildPath, '', $targetFile), '/');

        $this->manifest[$path] = $relativePath;
        $this->saveManifest();

        return '/' . ltrim($relativePath, '/');
    }
}
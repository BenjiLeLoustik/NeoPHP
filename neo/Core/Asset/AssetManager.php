<?php
declare(strict_types=1);

namespace Neo\Core\Asset;

use Neo\Core\Asset\Compiler\CssCompiler;
use Neo\Core\Asset\Compiler\JsCompiler;
use Neo\Core\Asset\Compiler\LessCompiler;
use Neo\Core\Asset\Exception\AssetException;
use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class AssetManager
{
    private Container $container;
    private string $sourcePath;
    private string $currentApplication;
    private string $buildPath;
    private string $manifestPath;
    /** @var array<string, string> */
    private array $manifest = [];
    private string $env;
    private string $publicPath;

    /** @var list<array{path: string, resolvedPath: string, compiled: bool, duration: float}> */
    private array $log = [];

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws AssetException
     * @throws ContainerException
     */
    public function __construct(Container $container)
    {
        $this->container = $container;

        $config = $this->container->get('asset.configModule')->from('app');
        $this->env = $config->get('environment', 'prod');

        $this->currentApplication = $this->container->get('application');
        $this->sourcePath = $this->container->get('assetsPath');
        $this->publicPath = $this->container->get('publicPath');
        $this->buildPath = $this->container->get('buildsPath') . $this->currentApplication . '/assets/';
        $this->manifestPath = $this->container->get('buildsPath') . $this->currentApplication . '/' . $this->container->get('manifestFilename');

        $this->loadManifest();
    }

    /**
     * @throws AssetException
     */
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

    /**
     * @throws AssetException
     */
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

    /**
     * @throws AssetException
     */
    public function getAssetPath(string $path): string
    {
        $start = microtime(true);

        if ($this->env === 'prod') {
            $resolved = $this->manifest[$path]
                ?? ('/builds/' . $this->currentApplication . '/assets/' . ltrim($path, '/'));

            $this->recordAsset($path, $resolved, false, $start);
            return $resolved;
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
            $this->recordAsset($path, $this->manifest[$path], false, $start);
            return $this->manifest[$path];
        }

        $resolved = $this->compile($path);
        $this->recordAsset($path, $resolved, true, $start);
        return $resolved;
    }

    /**
     * @throws AssetException
     */
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
            . ($targetFile
                    |> (fn (string $f): string => str_replace($this->buildPath, '', $f))
                    |> (fn (string $f): string => ltrim($f, '/'))
            );

        $this->manifest[$path] = $relativePath;
        $this->saveManifest();

        return '/' . ltrim($relativePath, '/');
    }

    private function recordAsset(string $path, string $resolvedPath, bool $compiled, float $start): void
    {
        $this->log[] = [
            'path' => $path,
            'resolvedPath' => $resolvedPath,
            'compiled' => $compiled,
            'duration' => round((microtime(true) - $start) * 1000, 2),
        ];
    }

    /**
     * @return list<array{path: string, resolvedPath: string, compiled: bool, duration: float}>
     */
    public function getAssetLog(): array
    {
        return $this->log;
    }
}
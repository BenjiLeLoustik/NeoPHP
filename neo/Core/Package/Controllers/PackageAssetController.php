<?php

declare(strict_types=1);

namespace Neo\Core\Package\Controllers;

use Neo\Core\Controller\AbstractController;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Http\Response\Types\Response;
use Neo\Core\Package\Interface\PackageInterface;
use Neo\Core\Routing\Attribute\MainRoute;
use Neo\Core\Routing\Attribute\Route;

#[MainRoute(path: '/packages-assets', name: 'packages.assets')]
final class PackageAssetController extends AbstractController
{
    #[Route(path: '/{package}/{path}', name: 'file', methods: ['GET'], requirements: ['path' => '.+'])]
    public function serve(string $package, string $path): Response
    {
        $assetsPath = $this->findPackageAssetsPath($package);

        if ($assetsPath === null) {
            return $this->make()->setStatusCode(404);
        }

        $realBase = realpath($assetsPath);
        $target = realpath($assetsPath . '/' . $path);

        if ($realBase === false || $target === false || !str_starts_with($target, $realBase . DIRECTORY_SEPARATOR)) {
            return $this->make()->setStatusCode(404);
        }

        if (!is_file($target)) {
            return $this->make()->setStatusCode(404);
        }

        $content = file_get_contents($target);

        if ($content === false) {
            return $this->make()->setStatusCode(500);
        }

        return $this->make()
            ->setHeader('Content-Type', $this->detectMimeType($target))
            ->setHeader('Cache-Control', 'public, max-age=86400')
            ->setContent($content);
    }

    private function detectMimeType(string $path): string
    {
        $extension = $path
                |> (fn (string $p): string => pathinfo($p, PATHINFO_EXTENSION))
                |> strtolower(...);

        return match ($extension) {
            'css' => 'text/css',
            'js' => 'application/javascript',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ico' => 'image/x-icon',
            default => mime_content_type($path) ?: 'application/octet-stream',
        };
    }

    /**
     * @throws \ReflectionException
     * @throws ContainerException
     */
    private function findPackageAssetsPath(string $packageName): ?string
    {
        if (!$this->container->has('packages')) {
            return null;
        }

        /** @var array<int, PackageInterface> $packages */
        $packages = $this->container->get('packages');

        foreach ($packages as $package) {
            if ($package->getName() === $packageName) {
                return $package->getAssetsPath();
            }
        }

        return null;
    }
}
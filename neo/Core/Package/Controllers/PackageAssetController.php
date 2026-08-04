<?php

namespace Neo\Core\Package\Controllers;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Http\Response\Types\Response;
use Neo\Core\Package\Interface\PackageInterface;
use Neo\Core\Routing\Attribute\MainRoute;
use Neo\Core\Routing\Attribute\Route;

#[MainRoute(path: '/packages-assets', name: 'packages.assets')]
class PackageAssetController extends AbstractController
{
    #[Route(path: '/{package}/{path}', name: 'file', methods: ['GET'], requirements: ['path' => '.+'])]
    public function serve(string $package, string $path): Response
    {
        $assetsPath = $this->findPackageAssetsPath($package);

        if ($assetsPath === null) {
            return $this->response()->setStatusCode(404);
        }

        $realBase = realpath($assetsPath);
        $target = realpath($assetsPath . '/' . $path);

        if ($realBase === false || $target === false || !str_starts_with($target, $realBase . DIRECTORY_SEPARATOR)) {
            return $this->response()->setStatusCode(404);
        }

        if (!is_file($target)) {
            return $this->response()->setStatusCode(404);
        }

        $mimeType = mime_content_type($target) ?: 'application/octet-stream';
        $content = file_get_contents($target);

        if ($content === false) {
            return $this->response()->setStatusCode(500);
        }

        return $this->response()
            ->setHeader('Content-Type', $mimeType)
            ->setHeader('Cache-Control', 'public, max-age=86400')
            ->setContent($content);
    }

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
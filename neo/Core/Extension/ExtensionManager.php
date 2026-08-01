<?php
declare(strict_types=1);

namespace Neo\Core\Extension;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;
use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\Utils\Scanner\ScannerAttributeManager;
use Neo\Core\View\Interface\TwigExtensionInterface;

final class ExtensionManager
{
    /** @var list<ControllerExtensionInterface>|null */
    private ?array $controllerExtensions = null;

    /** @var list<TwigExtensionInterface>|null */
    private ?array $viewExtensions = null;

    public function __construct(private readonly Container $container) {}

    /**
     * @return list<ControllerExtensionInterface>
     */
    public function getControllerExtensions(): array
    {
        return $this->controllerExtensions ??= $this->discover(ExtensionTypeEnum::CONTROLLER);
    }

    /**
     * @return list<TwigExtensionInterface>
     */
    public function getViewExtensions(): array
    {
        return $this->viewExtensions ??= $this->discover(ExtensionTypeEnum::VIEW);
    }

    public function applyToController(AbstractController $controller): void
    {
        foreach ($this->getControllerExtensions() as $extension) {
            $extension->extend($controller, $this->container);
        }
    }

    /**
     * @return list<object>
     */
    private function discover(ExtensionTypeEnum $type): array
    {
        $results = [];
        $basePath = realpath(__DIR__ . '/../../../');

        $scanPaths = [
            $basePath . '/neo',
            $basePath . '/src',
        ];

        foreach ($scanPaths as $scanPath) {
            if (!is_dir($scanPath)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($scanPath)
            );

            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                if (!str_ends_with($file->getFilename(), 'Extension.php')) {
                    continue;
                }

                $fqcn = $this->resolveFqcn($file->getRealPath());
                if ($fqcn === null) {
                    continue;
                }

                require_once $file->getRealPath();
                if (!class_exists($fqcn)) {
                    continue;
                }

                $ref = new \ReflectionClass($fqcn);
                if ($ref->isAbstract() || $ref->isInterface()) {
                    continue;
                }

                $scanResults = new ScannerAttributeManager($fqcn)
                    ->onClass()
                    ->withAttribute(Extension::class)
                    ->scan();

                if (empty($scanResults)) {
                    continue;
                }

                /** @var Extension $meta */
                $meta = $scanResults[0]->getAttribute();

                if ($meta->type !== $type) {
                    continue;
                }

                $results[] = $this->container->get($fqcn);
            }
        }

        return $results;
    }

    private function resolveFqcn(string $filePath): ?string
    {
        $src = file_get_contents($filePath);
        if ($src === false) {
            return null;
        }

        $namespace = '';
        if (preg_match('/namespace\s+([^;]+);/i', $src, $m)) {
            $namespace = trim($m[1]);
        }

        if (!preg_match('/class\s+([A-Za-z0-9_]+)/i', $src, $m)) {
            return null;
        }

        return $namespace !== '' ? $namespace . '\\' . trim($m[1]) : trim($m[1]);
    }
}
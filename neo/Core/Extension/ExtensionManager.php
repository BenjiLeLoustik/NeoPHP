<?php
declare(strict_types=1);

namespace Neo\Core\Extension;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\Package\Interface\PackageInterface;
use Neo\Core\Utils\Scanner\ScannerAttributeManager;
use Neo\Core\Utils\Scanner\ScannerFileManager;
use Neo\Core\View\Interface\TwigExtensionInterface;
use ReflectionException;

final class ExtensionManager
{
    /** @var list<ControllerExtensionInterface>|null */
    private ?array $controllerExtensions = null;

    /** @var list<TwigExtensionInterface>|null */
    private ?array $viewExtensions = null;

    public function __construct(private readonly Container $container) {}

    /**
     * @return list<ControllerExtensionInterface>
     * @throws ReflectionException
     */
    public function getControllerExtensions(): array
    {
        return $this->controllerExtensions ??= $this->discover(ExtensionTypeEnum::CONTROLLER);
    }

    /**
     * @return list<TwigExtensionInterface>
     * @throws ReflectionException
     */
    public function getViewExtensions(): array
    {
        return $this->viewExtensions ??= $this->discover(ExtensionTypeEnum::VIEW);
    }

    /**
     * @throws ReflectionException
     */
    public function applyToController(AbstractController $controller): void
    {
        foreach ($this->getControllerExtensions() as $extension) {
            $extension->extend($controller, $this->container);
        }
    }

    /**
     * @return list<object>
     * @throws ReflectionException
     * @throws ContainerException
     */
    private function discover(ExtensionTypeEnum $type): array
    {
        $results = [];
        $basePath = realpath(__DIR__ . '/../../../');

        $scanPaths = [
            $basePath . '/neo',
            $basePath . '/src',
        ];

        if ($this->container->has('packages')) {
            /** @var array<int, PackageInterface> $packages */
            $packages = $this->container->get('packages');

            foreach ($packages as $package) {
                $scanPaths[] = $package->getPath();
            }
        }

        $scanResults = new ScannerFileManager()
            ->paths($scanPaths)
            ->withFilenameSuffix('Extension.php')
            ->scan();

        foreach ($scanResults as $scanResult) {
            $fqcn = $scanResult->getFqcn();

            if (!class_exists($fqcn)) {
                continue;
            }

            $ref = new \ReflectionClass($fqcn);
            if ($ref->isAbstract() || $ref->isInterface()) {
                continue;
            }

            $attrResults = new ScannerAttributeManager($fqcn)
                ->onClass()
                ->withAttribute(Extension::class)
                ->scan();

            if (empty($attrResults)) {
                continue;
            }

            /** @var Extension $meta */
            $meta = $attrResults[0]->getAttribute();

            if ($meta->type !== $type) {
                continue;
            }

            $results[] = $this->container->get($fqcn);
        }

        return $results;
    }
}
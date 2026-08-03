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

        $scanResults = new ScannerFileManager()
            ->paths([$basePath . '/neo', $basePath . '/src'])
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
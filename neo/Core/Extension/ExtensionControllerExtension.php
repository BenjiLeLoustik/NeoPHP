<?php
declare(strict_types=1);

namespace Neo\Core\Extension;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;
use Neo\Core\Extension\Array\ArrayExtension;
use Neo\Core\Extension\Date\DateExtension;
use Neo\Core\Extension\File\FileExtension;
use Neo\Core\Extension\Html\HtmlExtension;
use Neo\Core\Extension\Json\JsonExtension;
use Neo\Core\Extension\Number\NumberExtension;
use Neo\Core\Extension\Path\PathExtension;
use Neo\Core\Extension\String\StringExtension;
use Neo\Core\Extension\Url\UrlExtension;

/**
 * @method \Neo\Core\Extension\String\StringExtension getString()
 * @method \Neo\Core\Extension\Array\ArrayExtension getArray()
 * @method \Neo\Core\Extension\Date\DateExtension getDate()
 * @method \Neo\Core\Extension\File\FileExtension getFile()
 * @method \Neo\Core\Extension\Html\HtmlExtension getHtml()
 * @method \Neo\Core\Extension\Json\JsonExtension getJson()
 * @method \Neo\Core\Extension\Number\NumberExtension getNumber()
 * @method \Neo\Core\Extension\Path\PathExtension getPath()
 * @method \Neo\Core\Extension\Url\UrlExtension getUrl()
 */
class ExtensionControllerExtension implements ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void
    {
        $controller->registerMethod('getString', fn() => $container->get(StringExtension::class));
        $controller->registerMethod('getArray',  fn() => $container->get(ArrayExtension::class));
        $controller->registerMethod('getDate',   fn() => $container->get(DateExtension::class));
        $controller->registerMethod('getFile',   fn() => $container->get(FileExtension::class));
        $controller->registerMethod('getHtml',   fn() => $container->get(HtmlExtension::class));
        $controller->registerMethod('getJson',   fn() => $container->get(JsonExtension::class));
        $controller->registerMethod('getNumber', fn() => $container->get(NumberExtension::class));
        $controller->registerMethod('getPath',   fn() => $container->get(PathExtension::class));
        $controller->registerMethod('getUrl',    fn() => $container->get(UrlExtension::class));
    }
}
<?php
declare(strict_types=1);

namespace Neo\Core\Http\File\Extension;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Exception\AbstractControllerException;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;
use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\Http\File\UploaderManager;
use Neo\Core\Http\Request\Request;

/**
 * @method string upload(string $field, string $name, array<int, string> $extensions, string $directory)
 */
#[Extension(type: ExtensionTypeEnum::CONTROLLER)]
class UploaderControllerExtension implements ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void
    {
        $controller->registerMethod('upload', function (
            string $field,
            string $name,
            array $extensions,
            string $directory
        ) use ($container) {
            $file = $container->get(Request::class)->file($field);

            if (!$file) {
                throw new AbstractControllerException(
                    title: 'File Upload Error',
                    message: sprintf("File field '%s' not found.", $field),
                    code: 400
                );
            }

            return $container->get(UploaderManager::class)->upload($file, $name, $extensions, $directory);
        });
    }
}
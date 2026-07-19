<?php
declare(strict_types=1);

namespace Neo\Core\Http;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Exception\AbstractControllerException;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;
use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\Http\Client\Cookie\Cookie;
use Neo\Core\Http\Client\Flash\Flash;
use Neo\Core\Http\Client\Session\Session;
use Neo\Core\Http\File\Uploader;
use Neo\Core\Http\Request\Request;
use Neo\Core\Http\Response\JsonResponse;

/**
 * @method \Neo\Core\Http\Client\Session\Session getSession()
 * @method \Neo\Core\Http\Client\Cookie\Cookie getCookie()
 * @method \Neo\Core\Http\Client\Flash\Flash getFlash()
 * @method \Neo\Core\Http\Response\JsonResponse json(array<string, mixed>|object $data, int $status = 200)
 * @method \Neo\Core\Http\Response\JsonResponse jsonSuccess(array<string, mixed>|object $data = [], int $status = 200)
 * @method \Neo\Core\Http\Response\JsonResponse jsonError(string $message, int $status = 400, array<string, mixed> $extra = [])
 * @method string upload(string $field, string $name, array<int, string> $extensions, string $directory)
 */
#[Extension(type: ExtensionTypeEnum::CONTROLLER)]
class HttpControllerExtension implements ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void
    {
        $controller->registerMethod('getSession', fn() => $container->get(Session::class));
        $controller->registerMethod('getCookie', fn() => $container->get(Cookie::class));
        $controller->registerMethod('getFlash', fn() => $container->get(Flash::class));

        $controller->registerMethod('json', function (array|object $data, int $status = 200) {
            return new JsonResponse($data, $status);
        });

        $controller->registerMethod('jsonSuccess', function (array|object $data = [], int $status = 200) {
            return new JsonResponse(['success' => true, 'data' => $data], $status);
        });

        $controller->registerMethod('jsonError', function (
            string $message,
            int $status = 400,
            array $extra = []
        ) {
            return new JsonResponse(array_merge(['success' => false, 'error' => $message], $extra), $status);
        });

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

            return $container->get(Uploader::class)->upload($file, $name, $extensions, $directory);
        });
    }
}
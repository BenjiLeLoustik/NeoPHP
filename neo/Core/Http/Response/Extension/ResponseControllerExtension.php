<?php
declare(strict_types=1);

namespace Neo\Core\Http\Response\Extension;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\DI\Container;
use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\Http\Response\ResponseManager;

/**
 * @method \Neo\Core\Http\Response\Types\JsonResponse json(array<string, mixed>|object $data, int $status = 200)
 * @method \Neo\Core\Http\Response\Types\JsonResponse jsonSuccess(array<string, mixed>|object $data = [], int $status = 200)
 * @method \Neo\Core\Http\Response\Types\JsonResponse jsonError(string $message, int $status = 400, array<string, mixed> $extra = [])
 */
#[Extension(type: ExtensionTypeEnum::CONTROLLER)]
class ResponseControllerExtension implements ControllerExtensionInterface
{
    public function extend(AbstractController $controller, Container $container): void
    {
        $manager = $container->get(ResponseManager::class);

        $controller->registerMethod('json', fn(array|object $data, int $status = 200) => $manager->json($data, $status));
        $controller->registerMethod('jsonSuccess', fn(array|object $data = [], int $status = 200) => $manager->jsonSuccess($data, $status));
        $controller->registerMethod('jsonError', fn(string $message, int $status = 400, array $extra = []) => $manager->jsonError($message, $status, $extra));
    }
}
<?php
declare(strict_types=1);

namespace Neo\Core\Http\Response;

use JsonException;
use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Http\Response\Types\JsonResponse;
use Neo\Core\Http\Response\Types\RedirectResponse;
use Neo\Core\Http\Response\Types\Response;
use ReflectionException;

final class ResponseManager
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, mixed>|object $data
     * @throws JsonException
     */
    public function json(array|object $data, int $status = 200): JsonResponse
    {
        return new JsonResponse($data, $status);
    }

    /**
     * @param array<string, mixed>|object $data
     * @throws JsonException
     */
    public function jsonSuccess(array|object $data = [], int $status = 200): JsonResponse
    {
        return new JsonResponse(['success' => true, 'data' => $data], $status);
    }

    /**
     * @param array<string, mixed> $extra
     * @throws JsonException
     */
    public function jsonError(string $message, int $status = 400, array $extra = []): JsonResponse
    {
        return new JsonResponse(array_merge(['success' => false, 'error' => $message], $extra), $status);
    }

    public function redirect(string $url, int $status = 302): RedirectResponse
    {
        return new RedirectResponse($url, $status);
    }

    /**
     * @throws ReflectionException
     * @throws ContainerException
     */
    public function make(): Response
    {
        return $this->container->get(Response::class);
    }
}
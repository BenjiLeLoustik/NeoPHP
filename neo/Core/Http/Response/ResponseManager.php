<?php
declare(strict_types=1);

namespace Neo\Core\Http\Response;

use Neo\Core\Http\Response\Types\JsonResponse;
use Neo\Core\Http\Response\Types\RedirectResponse;
use Neo\Core\Http\Response\Types\Response;

final class ResponseManager
{
    public function json(array|object $data, int $status = 200): JsonResponse
    {
        return new JsonResponse($data, $status);
    }

    public function jsonSuccess(array|object $data = [], int $status = 200): JsonResponse
    {
        return new JsonResponse(['success' => true, 'data' => $data], $status);
    }

    public function jsonError(string $message, int $status = 400, array $extra = []): JsonResponse
    {
        return new JsonResponse(array_merge(['success' => false, 'error' => $message], $extra), $status);
    }

    public function redirect(string $url, int $status = 302): RedirectResponse
    {
        return new RedirectResponse($url, $status);
    }

    public function make(): Response
    {
        return new Response();
    }
}
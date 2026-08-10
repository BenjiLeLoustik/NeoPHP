<?php

declare(strict_types=1);

namespace Neo\Core\Profiler\Controllers;

use Neo\Core\Controller\AbstractController;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Http\Response\Types\Response;
use Neo\Core\Profiler\ProfilerHtmlRenderer;
use Neo\Core\Routing\Attribute\MainRoute;
use Neo\Core\Routing\Attribute\Route;

#[MainRoute(path: '/_profiler', name: 'profiler')]
final class ProfilerPageController extends AbstractController
{
    /**
     * @throws \ReflectionException
     * @throws ContainerException
     */
    #[Route(path: '/{token}', name: 'show', methods: ['GET'])]
    public function show(string $token): Response
    {
        $renderer = new ProfilerHtmlRenderer();
        $path = $this->container->get('storagePath') . "/var/cache/profiler/{$token}.json";

        if (!file_exists($path)) {
            return $this->make()
                ->setStatusCode(404)
                ->setContent($renderer->renderNotFound($token));
        }

        $data = (string) file_get_contents($path)
                |> (fn (string $c): mixed => json_decode($c, true));

        return $this->make()
            ->setContent($renderer->render($data, $token));
    }
}
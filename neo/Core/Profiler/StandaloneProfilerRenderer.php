<?php

declare(strict_types=1);

namespace Neo\Core\Profiler;

use Neo\Core\Application\ApplicationDetector;
use Neo\Core\Application\ApplicationPaths;
use Neo\Core\DI\Container;
use Neo\Core\DI\ContainerRegistry;
use Neo\Core\Http\Request\Request;

final class StandaloneProfilerRenderer
{
    public function __construct(private readonly ProfilerHtmlRenderer $renderer = new ProfilerHtmlRenderer())
    {
    }

    public function handle(string $token): void
    {
        try {
            $paths = $this->resolveApplicationPaths();
        } catch (\Throwable) {
            $this->sendNotFound();
            return;
        }

        if (!$this->isDevEnvironment($paths['configsPath'])) {
            $this->sendNotFound();
            return;
        }

        $path = $paths['storagePath'] . "/var/cache/profiler/{$token}.json";

        if (!file_exists($path)) {
            http_response_code(404);
            echo $this->renderer->renderNotFound($token);
            return;
        }

        $data = json_decode((string) file_get_contents($path), true);

        http_response_code((int) ($data['status_code'] ?? 200));
        echo $this->renderer->render($data, $token);
    }

    /**
     * @return array{storagePath: string, configsPath: string}
     */
    private function resolveApplicationPaths(): array
    {
        $container = new Container();
        $container->set(Container::class, fn() => $container);
        ContainerRegistry::set($container);

        $container->set(Request::class, fn() => Request::fromGlobals());

        new ApplicationDetector($container)->detect();
        new ApplicationPaths($container)->register();

        return [
            'storagePath' => $container->get('storagePath'),
            'configsPath' => $container->get('configsPath'),
        ];
    }

    /**
     * Reads app.config.php directly, without booting ConfigModule, so this
     * check works even when the application fails to boot entirely.
     */
    private function isDevEnvironment(string $configsPath): bool
    {
        $file = rtrim($configsPath, '/\\') . '/app.config.php';

        if (!is_file($file)) {
            return false;
        }

        try {
            $config = require $file;
        } catch (\Throwable) {
            return false;
        }

        if (!is_array($config)) {
            return false;
        }

        return ($config['environment'] ?? null) === 'dev';
    }

    private function sendNotFound(): void
    {
        http_response_code(404);
        echo 'Not Found';
    }
}
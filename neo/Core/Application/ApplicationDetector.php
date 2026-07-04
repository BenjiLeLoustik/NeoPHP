<?php

namespace Neo\Core\Application;

use Neo\Core\Application\Exception\ApplicationException;
use Neo\Core\DI\Container;
use Neo\Core\Http\Request;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class ApplicationDetector
{
    public function __construct(
        private readonly Container $container
    ){}

    /**
     * @throws ApplicationException
     */
    public function detect(): void
    {
        if (php_sapi_name() === 'cli') {
            $this->detectFromCli();
            return;
        }

        $this->detectFromHttp();
    }

    /**
     * @throws ApplicationException
     */
    private function detectFromCli(): void
    {
        if (!empty($GLOBALS['_NEO_TEST_PROJECT'])) {
            $this->container->set('application', $GLOBALS['_NEO_TEST_PROJECT']);
            return;
        }

        global $argv;
        $project = null;

        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--project=')) {
                $project = substr($arg, strlen('--project='));
                break;
            }
        }

        if (!$project) {
            throw new ApplicationException(
                title: 'Application Error',
                message: 'You must pass --project=<ProjectName> in CLI.',
                code: 500
            );
        }

        $this->container->set('application', $project);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws ApplicationException
     * @throws NotFoundExceptionInterface
     */
    private function detectFromHttp(): void
    {
        $request = $this->container->get(Request::class);
        $serverData = $request->getServer() ?? $_SERVER;

        $serverName = $serverData['HTTP_HOST'] ?? $serverData['SERVER_NAME'] ?? null;
        $serverPort = (string) ($serverData['SERVER_PORT'] ?? '');

        if (!$serverName) {
            throw new ApplicationException(
                title: 'Application Error',
                message: "Unable to detect the server name.",
                code: 500
            );
        }

        $server = $serverName;
        if (!str_contains($server, ':') && !empty($serverPort) && !in_array($serverPort, ['80', '443'])) {
            $server .= ':' . $serverPort;
        }

        foreach (glob(__DIR__ . '/../../../src/*/Config/app.config.php') as $file) {
            $config = require $file;
            $accessServer = $config['access'] ?? null;

            if ($accessServer === $server) {
                $this->container->set('application', basename(dirname($file, 2)));
                return;
            }
        }

        throw new ApplicationException(
            title: 'Application Error',
            message: sprintf("No application detected for access: '%s'.", $server),
            code: 500
        );
    }
}
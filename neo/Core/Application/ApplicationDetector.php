<?php

namespace Neo\Core\Application;

use Neo\Core\Application\Exception\ApplicationException;
use Neo\Core\DI\Container;
use Neo\Core\Http\Request;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class ApplicationDetector
{
    private const string CACHE_FILE = '/storage/app-detect-cache.json';

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

        if (!empty($GLOBALS['_NEO_CLI_PROJECT'])) {
            $this->container->set('application', $GLOBALS['_NEO_CLI_PROJECT']);
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

        $rootDir = realpath(__DIR__ . '/../../../');
        $cacheFile = $rootDir . self::CACHE_FILE;

        $configFiles = glob($rootDir . '/src/*/Config/app.config.php');
        $signature = md5(implode('|', $configFiles) . '|' . implode('|', array_map('filemtime', $configFiles)));

        if (file_exists($cacheFile)) {
            $cached = json_decode(file_get_contents($cacheFile) ?: '', true);
            if (is_array($cached) && ($cached['signature'] ?? null) === $signature) {
                if (isset($cached['map'][$server])) {
                    $this->container->set('application', $cached['map'][$server]);
                    return;
                }
                throw new ApplicationException(
                    title: 'Application Error',
                    message: sprintf("No application detected for access: '%s'.", $server),
                    code: 500
                );
            }
        }

        $map = [];
        $found = null;

        foreach ($configFiles as $file) {
            $config = require $file;
            $accessServer = $config['access'] ?? null;

            if ($accessServer !== null) {
                $map[$accessServer] = basename(dirname($file, 2));
            }

            if ($accessServer === $server) {
                $found = basename(dirname($file, 2));
            }
        }

        file_put_contents($cacheFile, json_encode(['signature' => $signature, 'map' => $map]));

        if ($found !== null) {
            $this->container->set('application', $found);
            return;
        }

        throw new ApplicationException(
            title: 'Application Error',
            message: sprintf("No application detected for access: '%s'.", $server),
            code: 500
        );
    }
}
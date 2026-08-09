<?php
declare(strict_types=1);

define('NEO_START_TIME', microtime(true));
define('NEO_START_MEMORY', memory_get_usage(true));

require_once __DIR__ . '/../vendor/autoload.php';

use Neo\App;
use Neo\Core\Error\ErrorManager;
use Neo\Core\Profiler\StandaloneProfilerRenderer;

ErrorManager::registerBootstrap();

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestPath = parse_url($requestUri, PHP_URL_PATH) ?? '/';

if (preg_match('#^/_profiler/([a-f0-9]+)/?$#', $requestPath, $matches)) {
    new StandaloneProfilerRenderer()->handle($matches[1]);
    exit;
}

$app = new App();
$response = $app->run();
$response->send();
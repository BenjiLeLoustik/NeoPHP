<?php
declare(strict_types=1);

define('NEO_START_TIME',   microtime(true));
define('NEO_START_MEMORY', memory_get_usage(true));

require_once __DIR__ . '/../vendor/autoload.php';

use Neo\App;
use Neo\Core\Error\ErrorHandler;

ErrorHandler::registerBootstrap();

$app = new App();
$response = $app->run();
$response->send();

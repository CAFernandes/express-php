<?php

namespace Express\Test;

$path = __DIR__;
$path = explode(DIRECTORY_SEPARATOR, $path);
$path = array_slice($path, 0, count($path) - 2); // volta duas pastas
$path = implode(DIRECTORY_SEPARATOR, $path);
require_once $path . '/vendor/autoload.php';

use Express\Core\Application;
use Express\Services\CorsMiddleware;
use Express\Services\OpenApiDocsMiddleware;
use Express\Services\ErrorHandlerMiddleware;

$baseUrl = "https://{$_SERVER['SSL_TLS_SNI']}{$_SERVER['SCRIPT_NAME']}";
substr($baseUrl, -1) === '/' && $baseUrl = substr($baseUrl, 0, -1);
substr($baseUrl, -4) === '.php' && $baseUrl = substr($baseUrl, 0, -4);

$app = new Application($baseUrl);
$app->use(new ErrorHandlerMiddleware());
$app->use(new CorsMiddleware());
$app->use(function ($request, $response, $next) {
    $response->header('X-Powered-By', 'ExpressPHP');
    $next();
});

return $app;

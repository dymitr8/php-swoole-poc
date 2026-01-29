<?php

declare(strict_types=1);

use Swoole\HTTP\Server;
use Swoole\HTTP\Request;
use Swoole\HTTP\Response;
use App\Router\Router;
use App\Database\Connection;
use App\Controllers\MonitoringController;

require_once __DIR__ . '/vendor/autoload.php';

$config = require __DIR__ . '/config/server.php';
$dbConfig = require __DIR__ . '/config/database.php';

Connection::setConfig($dbConfig);

$server = new Server($config['host'], $config['port']);

$server->set([
    'worker_num' => $config['worker_num'],
    'daemonize' => $config['daemonize'],
]);

$router = new Router();

$router->get('/', function (Request $request, Response $response) {
    $response->header('Content-Type', 'application/json');
    $response->end(json_encode([
        'message' => 'Welcome to Swoole HTTP Server',
        'version' => '1.0.0',
    ]));
});

$router->get('/health', function (Request $request, Response $response) {
    $healthy = true;
    try {
        Connection::getInstance()->getConnection()->query('SELECT 1');
    } catch (\PDOException $e) {
        $healthy = false;
    }

    $response->header('Content-Type', 'application/json');
    $response->end(json_encode([
        'status' => $healthy ? 'healthy' : 'unhealthy',
        'timestamp' => date('c'),
    ]));
});

$router->get('/api/monitoring', [MonitoringController::class, 'index']);

$server->on('start', function (Server $server) use ($config) {
    echo "Swoole HTTP Server started at http://{$config['host']}:{$config['port']}\n";
});

$server->on('request', function (Request $request, Response $response) use ($router) {
    $response->header('Access-Control-Allow-Origin', '*');
    $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');

    if ($request->server['request_method'] === 'OPTIONS') {
        $response->status(204);
        $response->end();
        return;
    }

    $router->dispatch($request, $response);
});

$server->start();

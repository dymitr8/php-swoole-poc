<?php

declare(strict_types=1);

include_once __DIR__ . '/init.php';

use Swoole\HTTP\Server;
use Swoole\HTTP\Request;
use Swoole\HTTP\Response;
use Swoole\Timer;

$config = require __DIR__ . '/config/server.php';

$server = new Server($config['host'], $config['port']);
$server->set([
    'worker_num' => $config['worker_num'],
    'daemonize' => $config['daemonize'],
]);

$server->on('start', function () use ($config) {
    echo "Server started at http://{$config['host']}:{$config['port']}\n";
});

$timerInterval = (int)(getenv('UPTIME_TIMER_INTERVAL') ?: 900000);

$server->on('workerStart', function ($server, $workerId) use ($timerInterval) {
    if ($workerId !== 0) return;

    $pdo = create_pdo();
    $filled = fill_gaps($pdo);
    if ($filled > 0) echo "Filled {$filled} uptime records\n";

    Timer::tick($timerInterval, function () {
        insert_current_interval(create_pdo());
    });
});

$server->on('request', function (Request $request, Response $response) {
    $response->header('Content-Type', 'application/json');
    $response->header('Access-Control-Allow-Origin', '*');

    $uri = strtok($request->server['request_uri'], '?');

    switch ($uri) {
        case '/api/monitoring':
            process_monitoring($request, $response);
            break;
        default:
            $response->status(404);
            $response->end(json_encode(['error' => 'Not Found']));
    }
});

function process_monitoring(Request $request, Response $response): void
{
    $pdo = create_pdo();
    $params = $request->get ?? [];

    $tsMin = isset($params['ts_minimum']) ? (int)$params['ts_minimum'] : null;
    $tsMax = isset($params['ts_maximum']) ? (int)$params['ts_maximum'] : null;

    $records = select_uptime_history($pdo, $tsMin, $tsMax);
    $stats = calculate_stats($records);

    $response->end(json_encode([
        'result' => 'success',
        'output' => array_merge(['data' => $records], $stats),
    ]));
}

$server->start();

<?php

declare(strict_types=1);

return [
    'host' => '0.0.0.0',
    'port' => 9501,
    'worker_num' => swoole_cpu_num() * 2,
    'daemonize' => false,
];

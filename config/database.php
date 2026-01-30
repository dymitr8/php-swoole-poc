<?php

declare(strict_types=1);

$databaseUrl = getenv('DATABASE_URL');

if (!$databaseUrl) {
    throw new RuntimeException('DATABASE_URL environment variable is required');
}

$parsed = parse_url($databaseUrl);

return [
    'driver' => 'pgsql',
    'host' => $parsed['host'],
    'port' => (int) ($parsed['port'] ?? 5432),
    'database' => ltrim($parsed['path'], '/'),
    'username' => $parsed['user'],
    'password' => $parsed['pass'] ?? '',
];

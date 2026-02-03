<?php

declare(strict_types=1);

/**
 * Create a new PDO connection from DATABASE_URL environment variable.
 */
function create_pdo(): PDO
{
    $databaseUrl = getenv('DATABASE_URL');
    if (!$databaseUrl) {
        throw new RuntimeException('DATABASE_URL environment variable is required');
    }

    $parsed = parse_url($databaseUrl);
    $dsn = sprintf(
        'pgsql:host=%s;port=%d;dbname=%s',
        $parsed['host'],
        $parsed['port'] ?? 5432,
        ltrim($parsed['path'], '/')
    );

    return new PDO($dsn, $parsed['user'], $parsed['pass'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

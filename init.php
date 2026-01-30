<?php

declare(strict_types=1);

$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            putenv(trim($line));
        }
    }
}

require_once __DIR__ . '/vendor/autoload.php';

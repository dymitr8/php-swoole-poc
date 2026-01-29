<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Database\Connection;
use App\Database\Migrator;

echo "[LOG] Migration process started\n";

try {
    $dbConfig = require __DIR__ . '/config/database.php';
    echo "[LOG] Database config loaded\n";

    Connection::setConfig($dbConfig);
    $pdo = Connection::getInstance()->getConnection();
    echo "[LOG] Database connection established\n";
    
    $migrator = new Migrator($pdo);
    $migrator->runMigrations(__DIR__ . '/migrations');
    
    echo "\n[✓] All migrations completed successfully!\n";
} catch (Exception $e) {
    echo "[ERROR] Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
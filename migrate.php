<?php

declare(strict_types=1);

include_once __DIR__ . '/init.php';
include_once __DIR__ . '/source/migrate.php';

echo "[LOG] Migration process started\n";

try {
    $pdo = create_pdo();
    echo "[LOG] Database connection established\n";

    $migrator = new Migrator($pdo);
    $migrator->runMigrations(__DIR__ . '/migrations');

    echo "\n[OK] All migrations completed successfully!\n";
} catch (Exception $e) {
    echo "[ERROR] Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}

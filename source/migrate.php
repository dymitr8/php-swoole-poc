<?php

declare(strict_types=1);

class Migrator
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function runMigrations(string $migrationsPath): void
    {
        echo "[LOG] Starting migrations from: " . $migrationsPath . "\n";

        if (!is_dir($migrationsPath)) {
            throw new Exception("[ERROR] Migrations directory not found: " . $migrationsPath);
        }

        $files = glob($migrationsPath . '/*.sql');

        if (empty($files)) {
            throw new Exception("[ERROR] No SQL files found in: " . $migrationsPath);
        }

        echo "[LOG] Found " . count($files) . " migration files\n";
        sort($files);

        foreach ($files as $file) {
            echo "\n[LOG] Running migration: " . basename($file) . "\n";

            try {
                $sql = file_get_contents($file);
                echo "[LOG] SQL file size: " . strlen($sql) . " bytes\n";

                $this->pdo->exec($sql);
                echo "[OK] Migration completed successfully\n";
            } catch (Exception $e) {
                echo "[ERROR] Migration failed: " . $e->getMessage() . "\n";
                throw $e;
            }
        }

        echo "\n[LOG] All migrations completed!\n";
    }
}

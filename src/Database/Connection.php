<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;

class Connection
{
    private static ?Connection $instance = null;
    private static ?array $config = null;
    private static int $pid = 0;
    private PDO $pdo;

    private function __construct()
    {
        $this->connect();
    }

    private function connect(): void
    {
        if (self::$config === null) {
            throw new \RuntimeException('Database config not set');
        }

        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s',
            self::$config['driver'],
            self::$config['host'],
            self::$config['port'],
            self::$config['database']
        );

        try {
            $this->pdo = new PDO($dsn, self::$config['username'], self::$config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            self::$pid = getmypid();
        } catch (PDOException $e) {
            throw new PDOException('Database connection failed: ' . $e->getMessage());
        }
    }

    public static function setConfig(array $config): void
    {
        self::$config = $config;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null || self::$pid !== getmypid()) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getConnection(): PDO
    {
        try {
            $this->pdo->query('SELECT 1');
        } catch (PDOException $e) {
            $this->connect();
        }

        return $this->pdo;
    }

    private function __clone() {}

    public function __wakeup()
    {
        throw new \RuntimeException('Cannot unserialize singleton');
    }
}

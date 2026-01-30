<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use PDO;

class UptimeHistoryService
{
    private const INTERVAL_SECONDS = 900; // 15 minutes

    public function generateRandomData(): array
    {
        $fails = random_int(3, 6);
        $passes = 15 - $fails;

        return [
            'p' => $passes,
            'f' => $fails,
            'rl' => round(mt_rand(100, 300) / 1000, 8),
            'ra' => round(mt_rand(300, 600) / 1000, 8),
            'rh' => round(mt_rand(500, 2500) / 1000, 8),
        ];
    }

    public function getCurrentIntervalTimestamp(): int
    {
        return (int)(floor(time() / self::INTERVAL_SECONDS) * self::INTERVAL_SECONDS);
    }

    public function getLastRecordedTimestamp(): ?int
    {
        $pdo = Connection::getInstance()->getConnection();
        $stmt = $pdo->query('SELECT MAX(t) as max_t FROM uptime_history');
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['max_t'] ? (int)$result['max_t'] : null;
    }

    public function insertRecord(int $timestamp): bool
    {
        $pdo = Connection::getInstance()->getConnection();

        // Check if record already exists
        $checkStmt = $pdo->prepare('SELECT 1 FROM uptime_history WHERE t = :t LIMIT 1');
        $checkStmt->execute([':t' => $timestamp]);
        if ($checkStmt->fetch()) {
            return true; // Already exists, skip
        }

        $data = $this->generateRandomData();

        $sql = 'INSERT INTO uptime_history (t, p, f, rl, ra, rh)
                VALUES (:t, :p, :f, :rl, :ra, :rh)';

        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':t' => $timestamp,
            ':p' => $data['p'],
            ':f' => $data['f'],
            ':rl' => $data['rl'],
            ':ra' => $data['ra'],
            ':rh' => $data['rh'],
        ]);
    }

    public function fillGaps(): int
    {
        $lastTimestamp = $this->getLastRecordedTimestamp();
        $currentInterval = $this->getCurrentIntervalTimestamp();

        if ($lastTimestamp === null) {
            $this->insertRecord($currentInterval);
            return 1;
        }

        $count = 0;
        $nextTimestamp = $lastTimestamp + self::INTERVAL_SECONDS;

        while ($nextTimestamp <= $currentInterval) {
            $this->insertRecord($nextTimestamp);
            $nextTimestamp += self::INTERVAL_SECONDS;
            $count++;

            if ($count >= 1000) {
                break;
            }
        }

        return $count;
    }

    public function insertCurrentInterval(): bool
    {
        $currentInterval = $this->getCurrentIntervalTimestamp();
        return $this->insertRecord($currentInterval);
    }
}

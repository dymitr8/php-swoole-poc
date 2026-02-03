<?php

declare(strict_types=1);

define('INTERVAL_SECONDS', 900); // 15 minutes
define('SEVEN_DAYS', 604800);

/**
 * Generate random uptime data (simulated monitoring results)
 */
function generate_random_data(): array
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

/**
 * Get current 15-minute interval timestamp
 */
function get_current_interval(): int
{
    return (int)(floor(time() / INTERVAL_SECONDS) * INTERVAL_SECONDS);
}

/**
 * Get last recorded timestamp from database
 */
function get_last_timestamp(PDO $pdo): ?int
{
    $stmt = $pdo->query('SELECT MAX(t) as max_t FROM uptime_history');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['max_t'] ? (int)$result['max_t'] : null;
}

/**
 * Insert a single uptime record
 */
function insert_record(PDO $pdo, int $timestamp): bool
{
    $data = generate_random_data();
    $sql = 'INSERT INTO uptime_history (t, p, f, rl, ra, rh)
            VALUES (:t, :p, :f, :rl, :ra, :rh)
            ON CONFLICT (t) DO NOTHING';
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

/**
 * Fill any gaps in uptime history
 */
function fill_gaps(PDO $pdo): int
{
    $lastTimestamp = get_last_timestamp($pdo);
    $currentInterval = get_current_interval();

    if ($lastTimestamp === null) {
        insert_record($pdo, $currentInterval);
        return 1;
    }

    $count = 0;
    $nextTimestamp = $lastTimestamp + INTERVAL_SECONDS;

    while ($nextTimestamp <= $currentInterval) {
        insert_record($pdo, $nextTimestamp);
        $nextTimestamp += INTERVAL_SECONDS;
        $count++;
        if ($count >= 1000) {
            break;
        }
    }

    return $count;
}

/**
 * Insert current interval record
 */
function insert_current_interval(PDO $pdo): bool
{
    return insert_record($pdo, get_current_interval());
}

/**
 * Select uptime history records with optional time range filtering.
 * Uses hourly aggregation for ranges >= 7 days.
 */
function select_uptime_history(PDO $pdo, ?int $tsMin, ?int $tsMax): array
{
    $useHourlyAggregation = ($tsMin !== null && $tsMax !== null && ($tsMax - $tsMin) >= SEVEN_DAYS);
    $bindings = [];

    if ($useHourlyAggregation) {
        $sql = 'SELECT
            (floor(t / 3600) * 3600)::bigint AS t,
            SUM(p)::int AS p,
            SUM(f)::int AS f,
            MIN(rl) AS rl,
            AVG(ra) AS ra,
            MAX(rh) AS rh
        FROM uptime_history';
    } else {
        $sql = 'SELECT t, p, f, rl, ra, rh FROM uptime_history';
    }

    $conditions = [];
    if ($tsMin !== null) {
        $conditions[] = 't >= :ts_min';
        $bindings[':ts_min'] = $tsMin;
    }
    if ($tsMax !== null) {
        $conditions[] = 't <= :ts_max';
        $bindings[':ts_max'] = $tsMax;
    }
    if (!empty($conditions)) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    if ($useHourlyAggregation) {
        $sql .= ' GROUP BY floor(t / 3600)';
    }
    $sql .= ' ORDER BY t ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($bindings);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Calculate aggregate stats from records
 */
function calculate_stats(array &$records): array
{
    $failCount = 0;
    $passCount = 0;
    $runtimeMin = null;
    $runtimeMax = null;
    $runtimeSum = 0;

    foreach ($records as &$record) {
        $record['t'] = (int)$record['t'];
        $record['p'] = (int)$record['p'];
        $record['f'] = (int)$record['f'];

        $passCount += $record['p'];
        $failCount += $record['f'];

        $rl = (float)$record['rl'];
        $rh = (float)$record['rh'];
        $ra = (float)$record['ra'];

        if ($runtimeMin === null || $rl < $runtimeMin) {
            $runtimeMin = $rl;
        }
        if ($runtimeMax === null || $rh > $runtimeMax) {
            $runtimeMax = $rh;
        }
        $runtimeSum += $ra;
    }

    $count = count($records);

    return [
        'fail_count' => $failCount,
        'pass_count' => $passCount,
        'runtime_min' => $runtimeMin ?? 0,
        'runtime_max' => $runtimeMax ?? 0,
        'runtime_avg' => $count > 0 ? $runtimeSum / $count : 0,
    ];
}

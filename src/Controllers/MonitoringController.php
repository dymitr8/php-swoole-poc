<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use PDO;
use Swoole\HTTP\Request;
use Swoole\HTTP\Response;

class MonitoringController
{
    private const SEVEN_DAYS = 604800; 

    public function index(Request $request, Response $response): void
    {
        $pdo = Connection::getInstance()->getConnection();
        $params = $request->get ?? [];

        $tsMin = isset($params['ts_minimum']) ? (int)$params['ts_minimum'] : null;
        $tsMax = isset($params['ts_maximum']) ? (int)$params['ts_maximum'] : null;

        
        $useHourlyAggregation = false;
        if ($tsMin !== null && $tsMax !== null && ($tsMax - $tsMin) >= self::SEVEN_DAYS) {
            $useHourlyAggregation = true;
        }

        $bindings = [];

        if ($useHourlyAggregation) {
            
            $sql = 'SELECT
                (floor(t / 3600) * 3600)::bigint AS t,
                SUM(p)::int AS p,
                SUM(f)::int AS f,
                MIN(rl) AS rl,
                AVG(ra) AS ra,
                MAX(rh) AS rh
            FROM monitoring_uptime';

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

            $sql .= ' GROUP BY floor(t / 3600) ORDER BY t ASC';
        } else {
            
            $sql = 'SELECT t, p, f, rl, ra, rh FROM monitoring_uptime';

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

            $sql .= ' ORDER BY t ASC';
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        
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
        $runtimeAvg = $count > 0 ? $runtimeSum / $count : 0;

        $response->status(200);
        $response->header('Content-Type', 'application/json');
        $response->end(json_encode([
            'result' => 'success',
            'output' => [
                'data' => $records,
                'fail_count' => $failCount,
                'pass_count' => $passCount,
                'runtime_min' => $runtimeMin ?? 0,
                'runtime_max' => $runtimeMax ?? 0,
                'runtime_avg' => $runtimeAvg,
            ],
        ]));
    }
}

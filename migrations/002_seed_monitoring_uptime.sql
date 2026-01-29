-- Seed monitoring_uptime table with 365 days of 15-minute interval data
-- 96 intervals per day × 365 days = 35,040 records
-- 15 probes per interval with 20-40% failure rate
INSERT INTO monitoring_uptime (t, p, f, rl, ra, rh)
SELECT
    t,
    15 - f AS p,                                                    -- pass count
    f,                                                              -- fail count (3-6, ~20-40%)
    round((random() * 0.2 + 0.1)::numeric, 8) AS rl,               -- runtime low: 0.1-0.3s
    round((random() * 0.3 + 0.3)::numeric, 8) AS ra,               -- runtime avg: 0.3-0.6s
    round((random() * 2.0 + 0.5)::numeric, 8) AS rh                -- runtime high: 0.5-2.5s
FROM (
    SELECT
        (floor(extract(epoch from CURRENT_TIMESTAMP) / 900) * 900 - (n * 900))::bigint AS t,
        floor(random() * 4 + 3)::int AS f                          -- 3-6 fails per interval
    FROM generate_series(0, 35039) AS n
) AS intervals
ON CONFLICT DO NOTHING;

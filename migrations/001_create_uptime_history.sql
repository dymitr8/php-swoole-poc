-- Create uptime_history table (15-minute interval records)
CREATE TABLE IF NOT EXISTS uptime_history (
    id SERIAL PRIMARY KEY,
    t BIGINT NOT NULL,                          -- unix timestamp
    p INTEGER NOT NULL DEFAULT 0,               -- pass count
    f INTEGER NOT NULL DEFAULT 0,               -- fail count
    rl DECIMAL(10,8) NOT NULL DEFAULT 0,        -- runtime low (seconds)
    ra DECIMAL(10,8) NOT NULL DEFAULT 0,        -- runtime average (seconds)
    rh DECIMAL(10,8) NOT NULL DEFAULT 0,        -- runtime high (seconds)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create unique index on timestamp for faster lookups and to prevent duplicates
CREATE UNIQUE INDEX IF NOT EXISTS idx_uptime_history_t ON uptime_history(t DESC);

-- THE DEAD LAST — migration 002: Meshy backlog task events
-- Stores webhook events delivered by Meshy (image-to-3d + rigging tasks) so the
-- local models/characters/meshy-queue.sh puller can fetch completed tasks and
-- assemble the {name}_{m|f}.zip skins that build.sh imports.
--
-- One row per (task_id) — upserted on each webhook delivery so the latest
-- status/payload wins. `consumed_at` is set once the local machine has pulled
-- and downloaded the finished assets (so it is not handed out again).

CREATE TABLE IF NOT EXISTS meshy_tasks (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    task_id      TEXT UNIQUE NOT NULL,          -- Meshy task id (image-to-3d or rigging)
    task_type    TEXT,                          -- e.g. "image_to_3d", "rigging" (from payload)
    status       TEXT,                          -- PENDING|IN_PROGRESS|SUCCEEDED|FAILED|CANCELED
    progress     INTEGER DEFAULT 0,
    payload      TEXT NOT NULL,                 -- full task object JSON as delivered
    consumed_at  DATETIME,                      -- set when local machine has downloaded assets
    received_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_meshy_tasks_task_id ON meshy_tasks(task_id);
CREATE INDEX IF NOT EXISTS idx_meshy_tasks_status  ON meshy_tasks(status);
CREATE INDEX IF NOT EXISTS idx_meshy_tasks_consumed ON meshy_tasks(consumed_at);

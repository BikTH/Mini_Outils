-- 004_add_admin_challenge_id_to_attempts.sql
-- Add nullable admin_challenge_id to attempts to link attempts to a specific admin challenge
ALTER TABLE attempts
  ADD COLUMN admin_challenge_id INT NULL,
  ADD INDEX idx_attempts_admin_challenge_id (admin_challenge_id);

-- Optional FK to admin_challenges (nullable to remain non-destructive)
ALTER TABLE attempts
  ADD CONSTRAINT fk_attempts_admin_challenge FOREIGN KEY (admin_challenge_id) REFERENCES admin_challenges(id) ON DELETE SET NULL;

-- Notes:
-- - This is non-destructive: column is nullable and has an index for leaderboard queries.
-- - After deployment, server should start recording admin_challenge_id for attempts launched in that mode.

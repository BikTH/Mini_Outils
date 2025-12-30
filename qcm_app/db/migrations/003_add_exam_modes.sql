-- 003_add_exam_modes.sql
-- Adds exam mode support and timer fields to attempts, plus admin_challenges table
ALTER TABLE attempts
  ADD COLUMN mode VARCHAR(32) DEFAULT 'training',
  ADD COLUMN time_limit_seconds INT NULL,
  ADD COLUMN time_spent_seconds INT DEFAULT 0,
  ADD COLUMN is_forced_submit TINYINT(1) DEFAULT 0;

-- Table for admin-defined challenges
CREATE TABLE IF NOT EXISTS admin_challenges (
  id INT AUTO_INCREMENT PRIMARY KEY,
  exam_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  nb_questions INT NOT NULL DEFAULT 0,
  time_limit_seconds INT NULL,
  created_by VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
);

-- Notes:
-- - mode: 'training' | 'training_timed' | 'official' | 'admin_challenge'
-- - For existing attempts, new columns are nullable/defaulted to preserve history

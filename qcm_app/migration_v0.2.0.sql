-- Migration SQL pour qcm_app v0.2.0
-- Ajout de l'historique des tentatives et scoring partiel

CREATE TABLE IF NOT EXISTS attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    user_identifier VARCHAR(255) NULL,
    date_start DATETIME NOT NULL,
    date_end DATETIME NOT NULL,
    score_auto FLOAT NOT NULL,
    total_points FLOAT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_exam_id (exam_id),
    INDEX idx_user_identifier (user_identifier),
    INDEX idx_created_at (created_at)
);

CREATE TABLE IF NOT EXISTS attempt_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_option_ids TEXT NULL,
    is_full_correct TINYINT(1) NULL,
    partial_score FLOAT NOT NULL,
    FOREIGN KEY (attempt_id) REFERENCES attempts(id) ON DELETE CASCADE,
    INDEX idx_attempt_id (attempt_id),
    INDEX idx_question_id (question_id)
);


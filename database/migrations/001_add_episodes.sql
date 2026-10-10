-- Adds the episodes table to a database created before text-to-speech was added.
-- New databases get it from schema.sql and don't need this.
-- Run with: mysql -u <user> -p <database> < database/migrations/001_add_episodes.sql

CREATE TABLE IF NOT EXISTS episodes (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    course_id  INT UNSIGNED NULL,
    prompt     VARCHAR(500) NOT NULL,
    script     TEXT NOT NULL,
    audio_file VARCHAR(100) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- Deleted episodes are hidden but kept, so they still count toward the daily limit.
    deleted_at TIMESTAMP NULL,
    CONSTRAINT fk_episodes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_episodes_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE SET NULL,
    INDEX idx_episodes_user_created (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

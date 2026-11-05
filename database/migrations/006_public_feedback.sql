-- Store public website feedback, optionally associated with a branch.
-- Safe to run on the live database if the table already exists.
CREATE TABLE IF NOT EXISTS public_feedback (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(160) NOT NULL,
    email VARCHAR(190) NULL,
    branch_id BIGINT UNSIGNED NULL,
    rating TINYINT UNSIGNED NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_public_feedback_created (created_at),
    CONSTRAINT fk_public_feedback_branch
        FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

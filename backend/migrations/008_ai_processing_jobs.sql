-- Durable per-document processing state for short HTTP or scheduled CLI workers.
ALTER TABLE ai_document_uploads
  MODIFY status ENUM('uploaded','queued','processing','processed','failed','retained') NOT NULL DEFAULT 'uploaded',
  ADD COLUMN IF NOT EXISTS source_filename VARCHAR(255) NULL AFTER original_filename,
  ADD COLUMN IF NOT EXISTS source_reference CHAR(36) NULL AFTER source_filename,
  ADD COLUMN IF NOT EXISTS page_offset INT UNSIGNED NOT NULL DEFAULT 0 AFTER source_reference,
  ADD COLUMN IF NOT EXISTS page_count INT UNSIGNED NULL AFTER page_offset;

CREATE TABLE IF NOT EXISTS ai_processing_jobs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  job_reference CHAR(36) NOT NULL,
  document_id BIGINT UNSIGNED NOT NULL,
  status ENUM('queued','processing','completed','completed_with_warnings','retry_pending','failed') NOT NULL DEFAULT 'queued',
  stage VARCHAR(40) NOT NULL DEFAULT 'QUEUED',
  progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
  attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 3,
  error_message TEXT NULL,
  result_json JSON NULL,
  processing_ms INT UNSIGNED NULL,
  locked_until DATETIME NULL,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ai_job_reference (job_reference),
  UNIQUE KEY uq_ai_job_document (document_id),
  KEY idx_ai_job_queue (status,created_at),
  KEY idx_ai_job_lock (status,locked_until),
  CONSTRAINT fk_ai_job_document FOREIGN KEY (document_id) REFERENCES ai_document_uploads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_processing_batches (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  job_id BIGINT UNSIGNED NOT NULL,
  batch_index INT UNSIGNED NOT NULL,
  page_start INT UNSIGNED NULL,
  page_end INT UNSIGNED NULL,
  status ENUM('queued','processing','completed','failed') NOT NULL DEFAULT 'queued',
  result_json JSON NULL,
  error_message TEXT NULL,
  processing_ms INT UNSIGNED NULL,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ai_batch_job_index (job_id,batch_index),
  KEY idx_ai_batch_status (job_id,status),
  CONSTRAINT fk_ai_batch_job FOREIGN KEY (job_id) REFERENCES ai_processing_jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

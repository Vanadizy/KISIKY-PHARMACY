-- Run against the target pharmacy database in phpMyAdmin.
-- Creates or upgrades AI document extraction tables only.
-- Does not modify users, products, inventory, orders, payments, or settings.

-- Additive AI document extraction and order draft records.
-- Existing product, inventory, customer, order and proforma tables remain authoritative.
CREATE TABLE IF NOT EXISTS ai_document_uploads (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  document_reference CHAR(36) NOT NULL,
  source ENUM('customer','pos','inventory') NOT NULL,
  uploaded_by BIGINT UNSIGNED NULL,
  customer_id BIGINT UNSIGNED NULL,
  branch_id BIGINT UNSIGNED NOT NULL,
  original_filename VARCHAR(255) NOT NULL,
  storage_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL,
  sha256 CHAR(64) NOT NULL,
  status ENUM('uploaded','processing','processed','failed','retained') NOT NULL DEFAULT 'uploaded',
  extraction_json JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ai_document_reference (document_reference),
  KEY idx_ai_documents_branch (branch_id, created_at),
  KEY idx_ai_documents_customer (customer_id, created_at),
  CONSTRAINT fk_ai_documents_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_ai_documents_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  CONSTRAINT fk_ai_documents_branch FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_extracted_products (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  document_id BIGINT UNSIGNED NOT NULL,
  original_text TEXT NOT NULL,
  original_product_name VARCHAR(255) NULL,
  normalized_product_name VARCHAR(255) NULL,
  generic_name VARCHAR(255) NULL,
  brand_name VARCHAR(255) NULL,
  strength VARCHAR(100) NULL,
  dosage_form VARCHAR(100) NULL,
  packaging VARCHAR(150) NULL,
  requested_quantity DECIMAL(12,3) NULL,
  original_unit VARCHAR(80) NULL,
  interpreted_unit VARCHAR(80) NULL,
  unit_source ENUM('DOCUMENT','DEFAULT_ASSUMPTION','UNKNOWN') NOT NULL DEFAULT 'UNKNOWN',
  units_per_box INT UNSIGNED NULL,
  unit_price DECIMAL(14,2) NULL,
  total_price DECIMAL(14,2) NULL,
  category_name VARCHAR(150) NULL,
  page_number INT UNSIGNED NULL,
  column_number INT UNSIGNED NULL,
  line_number INT UNSIGNED NULL,
  extraction_confidence DECIMAL(5,4) NULL,
  uncertain_fields JSON NULL,
  is_crossed_out TINYINT(1) NOT NULL DEFAULT 0,
  is_duplicate TINYINT(1) NOT NULL DEFAULT 0,
  review_decision ENUM('pending','included','excluded') NOT NULL DEFAULT 'pending',
  match_status VARCHAR(32) NOT NULL DEFAULT 'NOT_FOUND',
  matched_product_id BIGINT UNSIGNED NULL,
  match_confidence DECIMAL(5,4) NULL,
  verification_notes TEXT NULL,
  reviewed_json JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ai_extracted_document (document_id, id),
  KEY idx_ai_extracted_match (matched_product_id),
  CONSTRAINT fk_ai_extracted_document FOREIGN KEY (document_id) REFERENCES ai_document_uploads(id) ON DELETE CASCADE,
  CONSTRAINT fk_ai_extracted_product FOREIGN KEY (matched_product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_product_matches (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  extracted_product_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(32) NOT NULL,
  confidence DECIMAL(5,4) NOT NULL,
  verification_notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ai_product_match (extracted_product_id, product_id),
  KEY idx_ai_match_product (product_id),
  CONSTRAINT fk_ai_match_extracted FOREIGN KEY (extracted_product_id) REFERENCES ai_extracted_products(id) ON DELETE CASCADE,
  CONSTRAINT fk_ai_match_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_order_drafts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  draft_reference CHAR(36) NOT NULL,
  document_id BIGINT UNSIGNED NOT NULL,
  source ENUM('customer','pos','inventory') NOT NULL,
  created_by BIGINT UNSIGNED NULL,
  customer_id BIGINT UNSIGNED NULL,
  branch_id BIGINT UNSIGNED NOT NULL,
  status ENUM('review','ready','submitted','cancelled') NOT NULL DEFAULT 'review',
  idempotency_key CHAR(64) NOT NULL,
  reviewed_payload JSON NULL,
  order_id BIGINT UNSIGNED NULL,
  proforma_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ai_draft_reference (draft_reference),
  UNIQUE KEY uq_ai_draft_idempotency (idempotency_key),
  KEY idx_ai_drafts_branch (branch_id, status, created_at),
  CONSTRAINT fk_ai_draft_document FOREIGN KEY (document_id) REFERENCES ai_document_uploads(id),
  CONSTRAINT fk_ai_draft_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_ai_draft_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  CONSTRAINT fk_ai_draft_branch FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_order_draft_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  draft_id BIGINT UNSIGNED NOT NULL,
  extracted_product_id BIGINT UNSIGNED NULL,
  product_id BIGINT UNSIGNED NULL,
  product_name_snapshot VARCHAR(255) NOT NULL,
  product_code_snapshot VARCHAR(80) NULL,
  quantity DECIMAL(12,3) NOT NULL,
  unit VARCHAR(80) NOT NULL DEFAULT 'BOX',
  include_in_order TINYINT(1) NOT NULL DEFAULT 1,
  review_json JSON NULL,
  KEY idx_ai_draft_items_draft (draft_id, id),
  KEY idx_ai_draft_items_product (product_id),
  CONSTRAINT fk_ai_draft_item_draft FOREIGN KEY (draft_id) REFERENCES ai_order_drafts(id) ON DELETE CASCADE,
  CONSTRAINT fk_ai_draft_item_extracted FOREIGN KEY (extracted_product_id) REFERENCES ai_extracted_products(id) ON DELETE SET NULL,
  CONSTRAINT fk_ai_draft_item_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_processing_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  document_id BIGINT UNSIGNED NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  customer_id BIGINT UNSIGNED NULL,
  branch_id BIGINT UNSIGNED NULL,
  model_name VARCHAR(100) NOT NULL,
  status ENUM('success','error','limit_reached') NOT NULL,
  input_tokens INT UNSIGNED NOT NULL DEFAULT 0,
  output_tokens INT UNSIGNED NOT NULL DEFAULT 0,
  estimated_cost_usd DECIMAL(12,6) NOT NULL DEFAULT 0,
  processing_ms INT UNSIGNED NOT NULL DEFAULT 0,
  safe_error_code VARCHAR(80) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ai_usage_day (created_at, status),
  KEY idx_ai_usage_branch (branch_id, created_at),
  CONSTRAINT fk_ai_log_document FOREIGN KEY (document_id) REFERENCES ai_document_uploads(id) ON DELETE SET NULL,
  CONSTRAINT fk_ai_log_user FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_ai_log_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  CONSTRAINT fk_ai_log_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (code,module_name,label) VALUES
('ai.orders.access','AI Order Assistant','Access AI Order Assistant');


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

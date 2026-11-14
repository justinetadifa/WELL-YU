-- wELYU wellness extension v1. PREPARED ONLY: explicit approval before import.
-- Additive tables only. No existing tables, accounts, passwords or scores change.
-- Existing 35-table WELL-YU schema must exist first. No fixtures are included.
USE `well_yu_db`;

CREATE TABLE IF NOT EXISTS welyu_configurations (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  created_by_user_id INT NOT NULL,
  name VARCHAR(190) NOT NULL,
  methodology_version VARCHAR(60) NOT NULL,
  payload_json JSON NOT NULL,
  validation_status VARCHAR(24) NOT NULL DEFAULT 'provisional',
  validation_reference VARCHAR(600) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wy_config_owner FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS welyu_services (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  owner_user_id INT NOT NULL,
  title VARCHAR(190) NOT NULL,
  category VARCHAR(80) NOT NULL,
  barangay VARCHAR(120) NOT NULL,
  lat DECIMAL(10,6) NULL,
  lng DECIMAL(10,6) NULL,
  payload_json JSON NOT NULL,
  fingerprint CHAR(64) NOT NULL,
  review_status VARCHAR(30) NOT NULL DEFAULT 'submitted',
  review_json JSON NULL,
  reviewer_user_id INT NULL,
  revision INT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wy_service_fingerprint (fingerprint),
  KEY ix_wy_service_status (review_status, category),
  CONSTRAINT fk_wy_service_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_wy_service_reviewer FOREIGN KEY (reviewer_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS welyu_needs (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  owner_user_id INT NOT NULL,
  title VARCHAR(190) NOT NULL,
  category VARCHAR(80) NOT NULL,
  barangay VARCHAR(120) NOT NULL,
  lat DECIMAL(10,6) NULL,
  lng DECIMAL(10,6) NULL,
  payload_json JSON NOT NULL,
  fingerprint CHAR(64) NOT NULL,
  review_status VARCHAR(30) NOT NULL DEFAULT 'submitted',
  review_json JSON NULL,
  reviewer_user_id INT NULL,
  revision INT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wy_need_fingerprint (fingerprint),
  KEY ix_wy_need_status (review_status, category),
  CONSTRAINT fk_wy_need_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_wy_need_reviewer FOREIGN KEY (reviewer_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS welyu_proposals (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  owner_user_id INT NOT NULL,
  title VARCHAR(190) NOT NULL,
  property_id INT NULL,
  service_id INT NULL,
  mode VARCHAR(20) NOT NULL,
  payload_json JSON NOT NULL,
  review_status VARCHAR(30) NOT NULL DEFAULT 'submitted',
  review_json JSON NULL,
  reviewer_user_id INT NULL,
  revision INT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_wy_proposal_owner (owner_user_id, created_at),
  CONSTRAINT fk_wy_proposal_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_wy_proposal_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE RESTRICT,
  CONSTRAINT fk_wy_proposal_service FOREIGN KEY (service_id) REFERENCES welyu_services(id) ON DELETE RESTRICT,
  CONSTRAINT fk_wy_proposal_reviewer FOREIGN KEY (reviewer_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS welyu_assessments (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  proposal_id INT NOT NULL,
  created_by_user_id INT NOT NULL,
  configuration_id INT NOT NULL,
  methodology_version VARCHAR(60) NOT NULL,
  snapshot_json JSON NOT NULL,
  result_json JSON NOT NULL,
  input_sha256 CHAR(64) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wy_assessment_snapshot (proposal_id, input_sha256),
  CONSTRAINT fk_wy_assessment_proposal FOREIGN KEY (proposal_id) REFERENCES welyu_proposals(id) ON DELETE RESTRICT,
  CONSTRAINT fk_wy_assessment_actor FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_wy_assessment_config FOREIGN KEY (configuration_id) REFERENCES welyu_configurations(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS welyu_scenarios (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  proposal_id INT NOT NULL,
  owner_user_id INT NOT NULL,
  baseline_id INT NULL,
  name VARCHAR(190) NOT NULL,
  is_baseline TINYINT(1) NOT NULL DEFAULT 0,
  input_json JSON NOT NULL,
  result_json JSON NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_wy_scenario_proposal (proposal_id, created_at),
  CONSTRAINT fk_wy_scenario_proposal FOREIGN KEY (proposal_id) REFERENCES welyu_proposals(id) ON DELETE RESTRICT,
  CONSTRAINT fk_wy_scenario_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_wy_scenario_baseline FOREIGN KEY (baseline_id) REFERENCES welyu_scenarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS welyu_comparisons (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  owner_user_id INT NOT NULL,
  name VARCHAR(190) NOT NULL,
  snapshot_json JSON NOT NULL,
  snapshot_sha256 CHAR(64) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wy_comparison_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS welyu_comparison_items (
  comparison_id INT NOT NULL,
  assessment_id INT NOT NULL,
  PRIMARY KEY (comparison_id, assessment_id),
  CONSTRAINT fk_wy_item_comparison FOREIGN KEY (comparison_id) REFERENCES welyu_comparisons(id) ON DELETE RESTRICT,
  CONSTRAINT fk_wy_item_assessment FOREIGN KEY (assessment_id) REFERENCES welyu_assessments(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS welyu_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  actor_user_id INT NOT NULL,
  service_id INT NULL,
  need_id INT NULL,
  proposal_id INT NULL,
  kind VARCHAR(40) NOT NULL,
  payload_json JSON NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_wy_event_proposal (proposal_id, created_at),
  CONSTRAINT fk_wy_event_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_wy_event_service FOREIGN KEY (service_id) REFERENCES welyu_services(id) ON DELETE RESTRICT,
  CONSTRAINT fk_wy_event_need FOREIGN KEY (need_id) REFERENCES welyu_needs(id) ON DELETE RESTRICT,
  CONSTRAINT fk_wy_event_proposal FOREIGN KEY (proposal_id) REFERENCES welyu_proposals(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS welyu_documents (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  uploader_user_id INT NOT NULL,
  service_id INT NULL,
  need_id INT NULL,
  proposal_id INT NULL,
  original_name VARCHAR(190) NOT NULL,
  storage_name VARCHAR(80) NOT NULL,
  mime_type VARCHAR(80) NOT NULL,
  size_bytes INT UNSIGNED NOT NULL,
  sha256 CHAR(64) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wy_document_storage (storage_name),
  CONSTRAINT fk_wy_document_uploader FOREIGN KEY (uploader_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_wy_document_service FOREIGN KEY (service_id) REFERENCES welyu_services(id) ON DELETE RESTRICT,
  CONSTRAINT fk_wy_document_need FOREIGN KEY (need_id) REFERENCES welyu_needs(id) ON DELETE RESTRICT,
  CONSTRAINT fk_wy_document_proposal FOREIGN KEY (proposal_id) REFERENCES welyu_proposals(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

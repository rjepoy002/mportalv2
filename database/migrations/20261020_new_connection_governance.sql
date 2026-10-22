CREATE TABLE new_connection_notice_versions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 notice_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 version VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 content_utf8 LONGTEXT NOT NULL, content_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 language VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 approval_evidence VARCHAR(160) NOT NULL, approved_by BIGINT UNSIGNED NOT NULL, publisher_id BIGINT UNSIGNED NOT NULL,
 approved_at DATETIME(6) NOT NULL, effective_at DATETIME(6) NOT NULL, created_at DATETIME(6) NOT NULL,
 UNIQUE KEY nc7_notice_version(notice_code,version), UNIQUE KEY nc7_notice_content(notice_code,version,content_sha256),
 FOREIGN KEY(approved_by) REFERENCES users(id) ON DELETE RESTRICT,
 FOREIGN KEY(publisher_id) REFERENCES users(id) ON DELETE RESTRICT,
 CHECK(BINARY content_sha256=BINARY SHA2(content_utf8,256))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement
CREATE TABLE new_connection_catalog_versions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 kind ENUM('localities','eligibility','premises','occupancy','rules','contract') NOT NULL,
 version VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 content_json LONGTEXT NOT NULL, content_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 approval_evidence VARCHAR(160) NOT NULL, approved_by BIGINT UNSIGNED NOT NULL, publisher_id BIGINT UNSIGNED NOT NULL,
 approved_at DATETIME(6) NOT NULL, effective_at DATETIME(6) NOT NULL, created_at DATETIME(6) NOT NULL,
 UNIQUE KEY nc7_catalog_version(kind,version), UNIQUE KEY nc7_catalog_content(id,kind,content_sha256),
 FOREIGN KEY(approved_by) REFERENCES users(id) ON DELETE RESTRICT,
 FOREIGN KEY(publisher_id) REFERENCES users(id) ON DELETE RESTRICT,
 CHECK(JSON_VALID(content_json)), CHECK(BINARY content_sha256=BINARY SHA2(content_json,256))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement
CREATE TABLE new_connection_publication_bundles (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 version VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 contract_version VARCHAR(100) NOT NULL, manifest_json LONGTEXT NOT NULL,
 manifest_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 approval_evidence VARCHAR(160) NOT NULL, publisher_id BIGINT UNSIGNED NOT NULL, created_at DATETIME(6) NOT NULL,
 FOREIGN KEY(publisher_id) REFERENCES users(id) ON DELETE RESTRICT,
 CHECK(JSON_VALID(manifest_json)), CHECK(BINARY manifest_sha256=BINARY SHA2(manifest_json,256))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement
CREATE TABLE new_connection_bundle_catalogs (
 bundle_id BIGINT UNSIGNED NOT NULL, kind ENUM('localities','eligibility','premises','occupancy','rules','contract') NOT NULL,
 catalog_id BIGINT UNSIGNED NOT NULL, content_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 PRIMARY KEY(bundle_id,kind),
 FOREIGN KEY(bundle_id) REFERENCES new_connection_publication_bundles(id) ON DELETE RESTRICT,
 FOREIGN KEY(catalog_id,kind,content_sha256) REFERENCES new_connection_catalog_versions(id,kind,content_sha256) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement
CREATE TABLE new_connection_bundle_notices (
 bundle_id BIGINT UNSIGNED NOT NULL, notice_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 version VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 content_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 PRIMARY KEY(bundle_id,notice_code),
 FOREIGN KEY(bundle_id) REFERENCES new_connection_publication_bundles(id) ON DELETE RESTRICT,
 FOREIGN KEY(notice_code,version,content_sha256) REFERENCES new_connection_notice_versions(notice_code,version,content_sha256) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement
CREATE TABLE new_connection_catalog_retirements (
 catalog_id BIGINT UNSIGNED PRIMARY KEY, actor_user_id BIGINT UNSIGNED NOT NULL,
 evidence_reference VARCHAR(160) NOT NULL, occurred_at DATETIME(6) NOT NULL,
 FOREIGN KEY(catalog_id) REFERENCES new_connection_catalog_versions(id) ON DELETE RESTRICT,
 FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement
CREATE TABLE new_connection_publication_heads (
 scope VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 epoch BIGINT UNSIGNED NOT NULL DEFAULT 0, bundle_id BIGINT UNSIGNED NULL,
 state ENUM('withdrawn','published') NOT NULL,
 last_action ENUM('publish','withdraw','retire_catalog') NULL, catalog_id BIGINT UNSIGNED NULL,
 updated_by BIGINT UNSIGNED NULL, updated_at DATETIME(6) NOT NULL,
 FOREIGN KEY(bundle_id) REFERENCES new_connection_publication_bundles(id) ON DELETE RESTRICT,
 FOREIGN KEY(catalog_id) REFERENCES new_connection_catalog_versions(id) ON DELETE RESTRICT,
 FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE RESTRICT,
 CHECK(scope='STAGE1'), CHECK(state<>'published' OR bundle_id IS NOT NULL),
 CHECK(epoch=0 OR (updated_by IS NOT NULL AND last_action IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement
CREATE TABLE new_connection_publication_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, scope VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 epoch BIGINT UNSIGNED NOT NULL, action ENUM('publish','withdraw','retire_catalog') NOT NULL,
 state ENUM('withdrawn','published') NOT NULL, bundle_id BIGINT UNSIGNED NULL, catalog_id BIGINT UNSIGNED NULL,
 actor_user_id BIGINT UNSIGNED NOT NULL, occurred_at DATETIME(6) NOT NULL,
 UNIQUE KEY nc7_publication_epoch(scope,epoch),
 UNIQUE KEY nc7_publication_bundle(scope,epoch,bundle_id),
 FOREIGN KEY(scope) REFERENCES new_connection_publication_heads(scope) ON DELETE RESTRICT,
 FOREIGN KEY(bundle_id) REFERENCES new_connection_publication_bundles(id) ON DELETE RESTRICT,
 FOREIGN KEY(catalog_id) REFERENCES new_connection_catalog_versions(id) ON DELETE RESTRICT,
 FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement
CREATE TABLE new_connection_submission_publications (
 application_id BIGINT UNSIGNED NOT NULL, revision_id BIGINT UNSIGNED NOT NULL, applicant_user_id BIGINT UNSIGNED NOT NULL,
 bundle_id BIGINT UNSIGNED NOT NULL, scope VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 epoch BIGINT UNSIGNED NOT NULL, proof_key_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 occurred_at DATETIME(6) NOT NULL, PRIMARY KEY(application_id,revision_id),
 FOREIGN KEY(application_id,revision_id) REFERENCES new_connection_application_revisions(application_id,id) ON DELETE RESTRICT,
 FOREIGN KEY(application_id,applicant_user_id) REFERENCES new_connection_applications(id,applicant_user_id) ON DELETE RESTRICT,
 FOREIGN KEY(bundle_id) REFERENCES new_connection_publication_bundles(id) ON DELETE RESTRICT,
 FOREIGN KEY(scope,epoch,bundle_id) REFERENCES new_connection_publication_events(scope,epoch,bundle_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

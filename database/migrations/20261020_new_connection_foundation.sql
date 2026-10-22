-- Reviewed DDL template. Execute only through the isolated migration runner.
-- Each statement is separated explicitly so trigger bodies need no DELIMITER.
CREATE TABLE IF NOT EXISTS new_connection_applications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 public_reference CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 applicant_user_id BIGINT UNSIGNED NOT NULL,
 creation_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 lifecycle_status ENUM('draft','submitted','processing','scheduled','rejected','withdrawn') NOT NULL,
 workflow_version SMALLINT UNSIGNED NOT NULL,
 current_revision_id BIGINT UNSIGNED NULL,
 revision_no INT UNSIGNED NOT NULL DEFAULT 1,
 office_code VARCHAR(32) NOT NULL DEFAULT 'UNASSIGNED',
 lock_version INT UNSIGNED NOT NULL DEFAULT 0,
 created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, submitted_at DATETIME(6) NULL,
 UNIQUE KEY nc_app_owner (id, applicant_user_id),
 UNIQUE KEY nc_create_request (applicant_user_id, creation_key),
 KEY nc_app_queue (office_code,lifecycle_status,updated_at),
 CONSTRAINT nc_app_user FOREIGN KEY (applicant_user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CHECK(workflow_version=1), CHECK(revision_no>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement
CREATE TABLE IF NOT EXISTS new_connection_application_revisions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 application_id BIGINT UNSIGNED NOT NULL, revision_no INT UNSIGNED NOT NULL,
 schema_version SMALLINT UNSIGNED NOT NULL,
 snapshot_json LONGTEXT NOT NULL, snapshot_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 submission_state ENUM('draft','submitted') NOT NULL,
 created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME(6) NOT NULL, submitted_at DATETIME(6) NULL,
 UNIQUE KEY nc_revision_number(application_id,revision_no), UNIQUE KEY nc_revision_app(application_id,id),
 CONSTRAINT nc_revision_application FOREIGN KEY(application_id) REFERENCES new_connection_applications(id) ON DELETE RESTRICT,
 CONSTRAINT nc_revision_actor FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT,
 CHECK(JSON_VALID(snapshot_json)), CHECK(revision_no>=1), CHECK(schema_version>=1),
 CHECK((submission_state='draft' AND submitted_at IS NULL) OR (submission_state='submitted' AND submitted_at IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement
CREATE TABLE IF NOT EXISTS new_connection_stages (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 application_id BIGINT UNSIGNED NOT NULL, stage_code TINYINT UNSIGNED NOT NULL,
 status ENUM('not_started','in_progress','submitted','under_review','needs_action','on_hold','approved','rejected') NOT NULL,
 revision_id BIGINT UNSIGNED NOT NULL, lock_version INT UNSIGNED NOT NULL DEFAULT 0,
 decided_by BIGINT UNSIGNED NULL, decision_reason VARCHAR(1000) NULL, decided_at DATETIME(6) NULL,
 updated_at DATETIME(6) NOT NULL,
 UNIQUE KEY nc_stage_number(application_id,stage_code), UNIQUE KEY nc_stage_app(application_id,id),
 KEY nc_stage_queue(status,stage_code),
 CONSTRAINT nc_stage_application FOREIGN KEY(application_id) REFERENCES new_connection_applications(id) ON DELETE RESTRICT,
 CONSTRAINT nc_stage_revision FOREIGN KEY(application_id,revision_id) REFERENCES new_connection_application_revisions(application_id,id) ON DELETE RESTRICT,
 CONSTRAINT nc_stage_actor FOREIGN KEY(decided_by) REFERENCES users(id) ON DELETE RESTRICT,
 CHECK(stage_code BETWEEN 1 AND 7)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement
CREATE TABLE IF NOT EXISTS new_connection_stage_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 application_id BIGINT UNSIGNED NOT NULL, stage_id BIGINT UNSIGNED NOT NULL,
 revision_id BIGINT UNSIGNED NOT NULL, actor_user_id BIGINT UNSIGNED NOT NULL,
 from_status VARCHAR(20) NOT NULL, to_status VARCHAR(20) NOT NULL,
 reason VARCHAR(1000) NOT NULL, stage_version INT UNSIGNED NOT NULL,
 idempotency_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 request_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 occurred_at DATETIME(6) NOT NULL,
 UNIQUE KEY nc_stage_request(application_id,idempotency_key),
 KEY nc_stage_history(application_id,id),
 CONSTRAINT nc_event_stage FOREIGN KEY(application_id,stage_id) REFERENCES new_connection_stages(application_id,id) ON DELETE RESTRICT,
 CONSTRAINT nc_event_revision FOREIGN KEY(application_id,revision_id) REFERENCES new_connection_application_revisions(application_id,id) ON DELETE RESTRICT,
 CONSTRAINT nc_event_actor FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement
CREATE TABLE IF NOT EXISTS new_connection_audit_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 application_id BIGINT UNSIGNED NULL, actor_user_id BIGINT UNSIGNED NOT NULL,
 action_code VARCHAR(64) NOT NULL, entity_type VARCHAR(32) NOT NULL, entity_id BIGINT UNSIGNED NOT NULL,
 safe_metadata_json LONGTEXT NOT NULL, occurred_at DATETIME(6) NOT NULL,
 KEY nc_audit_history(application_id,id),
 CONSTRAINT nc_audit_application FOREIGN KEY(application_id) REFERENCES new_connection_applications(id) ON DELETE RESTRICT,
 CONSTRAINT nc_audit_actor FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CHECK(JSON_VALID(safe_metadata_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement
CREATE TABLE IF NOT EXISTS new_connection_consents (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 application_id BIGINT UNSIGNED NOT NULL, revision_id BIGINT UNSIGNED NOT NULL,
 applicant_user_id BIGINT UNSIGNED NOT NULL,
 acknowledgment_code VARCHAR(64) NOT NULL, document_version VARCHAR(100) NOT NULL,
 document_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 acknowledged_at DATETIME(6) NOT NULL,
 UNIQUE KEY nc_ack_version(application_id,revision_id,acknowledgment_code,document_version),
 CONSTRAINT nc_consent_owner FOREIGN KEY(application_id,applicant_user_id) REFERENCES new_connection_applications(id,applicant_user_id) ON DELETE RESTRICT,
 CONSTRAINT nc_consent_revision FOREIGN KEY(application_id,revision_id) REFERENCES new_connection_application_revisions(application_id,id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement
CREATE TABLE IF NOT EXISTS new_connection_roles (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code VARCHAR(64) NOT NULL UNIQUE, description VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement
CREATE TABLE IF NOT EXISTS new_connection_permissions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code VARCHAR(64) NOT NULL UNIQUE, description VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement
CREATE TABLE IF NOT EXISTS new_connection_role_permissions (
 role_id BIGINT UNSIGNED NOT NULL, permission_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(role_id,permission_id),
 CONSTRAINT nc_rp_role FOREIGN KEY(role_id) REFERENCES new_connection_roles(id) ON DELETE RESTRICT,
 CONSTRAINT nc_rp_permission FOREIGN KEY(permission_id) REFERENCES new_connection_permissions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement
CREATE TABLE IF NOT EXISTS new_connection_staff_assignments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL, role_id BIGINT UNSIGNED NOT NULL,
 office_scope VARCHAR(32) NOT NULL, granted_by BIGINT UNSIGNED NOT NULL,
 granted_at DATETIME(6) NOT NULL, expires_at DATETIME(6) NULL,
 revoked_at DATETIME(6) NULL, revoked_by BIGINT UNSIGNED NULL, revocation_reason VARCHAR(1000) NULL,
 KEY nc_staff_lookup(user_id,revoked_at,office_scope),
 CONSTRAINT nc_staff_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT nc_staff_role FOREIGN KEY(role_id) REFERENCES new_connection_roles(id) ON DELETE RESTRICT,
 CONSTRAINT nc_staff_grantor FOREIGN KEY(granted_by) REFERENCES users(id) ON DELETE RESTRICT,
 CONSTRAINT nc_staff_revoker FOREIGN KEY(revoked_by) REFERENCES users(id) ON DELETE RESTRICT,
 CHECK((revoked_at IS NULL AND revoked_by IS NULL AND revocation_reason IS NULL) OR
       (revoked_at IS NOT NULL AND revoked_by IS NOT NULL AND CHAR_LENGTH(revocation_reason)>0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- statement
CREATE TABLE IF NOT EXISTS new_connection_staff_assignment_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, assignment_id BIGINT UNSIGNED NOT NULL,
 actor_user_id BIGINT UNSIGNED NOT NULL, event_type ENUM('granted','revoked') NOT NULL,
 reason VARCHAR(1000) NOT NULL, occurred_at DATETIME(6) NOT NULL,
 CONSTRAINT nc_assignment_event FOREIGN KEY(assignment_id) REFERENCES new_connection_staff_assignments(id) ON DELETE RESTRICT,
 CONSTRAINT nc_assignment_actor FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

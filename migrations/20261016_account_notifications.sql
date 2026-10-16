-- Portal-owned notification events, committed with link/unlink transitions.
CREATE TABLE IF NOT EXISTS mco_portal.account_notifications (
    event_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    event_type ENUM('linked','unlinked') NOT NULL,
    recipient_email VARCHAR(254) NOT NULL,
    recipient_name VARCHAR(255) NOT NULL,
    account_code VARCHAR(12) NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    occurred_at BIGINT UNSIGNED NOT NULL,
    delivery_state ENUM('pending','sending','accepted','failed') NOT NULL DEFAULT 'pending',
    attempted_at BIGINT UNSIGNED NULL,
    KEY idx_notification_state (delivery_state, occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

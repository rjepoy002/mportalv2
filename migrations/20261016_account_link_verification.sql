-- Portal-owned state only. Run against mco_portal before enabling the feature.
-- No changes to master, billhistory, arledger or any other operational table.
CREATE TABLE IF NOT EXISTS mco_portal.account_link_challenges (
    user_id BIGINT UNSIGNED NOT NULL,
    challenge_id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    AcctNo VARCHAR(15) NOT NULL,
    account_code VARCHAR(10) NOT NULL,
    meter_serial VARCHAR(20) NOT NULL,
    friendly_label VARCHAR(100) NULL,
    allowed_months TEXT NOT NULL,
    created_at BIGINT UNSIGNED NOT NULL,
    expires_at BIGINT UNSIGNED NOT NULL,
    failures INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_link_challenge (challenge_id),
    KEY idx_link_challenge_expiry (expires_at),
    CONSTRAINT fk_link_challenge_user FOREIGN KEY (user_id)
        REFERENCES mco_portal.users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mco_portal.account_link_limits (
    scope_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    window_started BIGINT UNSIGNED NOT NULL,
    blocked_until BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (scope_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

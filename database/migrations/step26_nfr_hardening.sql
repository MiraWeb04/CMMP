USE cmmp_db;

CREATE TABLE IF NOT EXISTS login_attempts (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	identifier VARCHAR(255) NOT NULL,
	ip_address VARCHAR(45) NOT NULL,
	was_successful TINYINT(1) NOT NULL DEFAULT 0,
	attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	KEY idx_login_attempts_lookup (identifier, attempted_at),
	KEY idx_login_attempts_ip (ip_address, attempted_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	user_id BIGINT UNSIGNED NOT NULL,
	token_hash CHAR(64) NOT NULL,
	expires_at DATETIME NOT NULL,
	used_at DATETIME NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_password_reset_hash (token_hash),
	KEY idx_password_reset_user (user_id, expires_at),
	CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users (id)
		ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

ALTER TABLE members ADD INDEX IF NOT EXISTS idx_members_email (email);
ALTER TABLE events ADD INDEX IF NOT EXISTS idx_events_title (title);
ALTER TABLE service_requests ADD INDEX IF NOT EXISTS idx_service_requests_requested_at (requested_at);
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_users_last_login (last_login_at);

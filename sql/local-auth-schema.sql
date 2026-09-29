-- ATLAS Installation Server local authentication schema
-- Run once against atlas_install_panda using a database account with CREATE/ALTER privileges.
CREATE TABLE IF NOT EXISTS atlas_local_user (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, username VARCHAR(64) NOT NULL,
  first_name VARCHAR(100) NOT NULL DEFAULT '', last_name VARCHAR(100) NOT NULL DEFAULT '',
  email VARCHAR(254) NOT NULL DEFAULT '', role VARCHAR(32) NOT NULL DEFAULT 'user',
  enabled TINYINT(1) NOT NULL DEFAULT 1, password_hash VARCHAR(255) NOT NULL,
  must_change_password TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(id), UNIQUE KEY uq_atlas_local_username(username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS atlas_local_totp (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, user_id BIGINT UNSIGNED NOT NULL,
  label VARCHAR(100) NOT NULL DEFAULT 'Authenticator', secret_enc TEXT NOT NULL, enabled TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id), KEY ix_atlas_totp_user(user_id),
  CONSTRAINT fk_atlas_totp_user FOREIGN KEY(user_id) REFERENCES atlas_local_user(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS atlas_local_session (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, user_id BIGINT UNSIGNED NOT NULL, token_hash CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL, remote_addr VARCHAR(64) NOT NULL DEFAULT '', user_agent VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY(id), UNIQUE KEY uq_atlas_session_token(token_hash), KEY ix_atlas_session_user(user_id),
  KEY ix_atlas_session_expires(expires_at), CONSTRAINT fk_atlas_session_user FOREIGN KEY(user_id) REFERENCES atlas_local_user(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS atlas_auth_setting (
  name VARCHAR(64) NOT NULL, value VARCHAR(255) NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY(name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'admin', 'user') NOT NULL DEFAULT 'user'
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS login_attempts (
    address_hash CHAR(64) CHARACTER SET ascii PRIMARY KEY,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    window_start BIGINT UNSIGNED NOT NULL,
    INDEX (window_start)
) ENGINE=InnoDB;

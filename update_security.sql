USE isp_db;

-- Audit log: tracks every privileged action
CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actor_id INT,
    actor_name VARCHAR(100),
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(50),
    target_id INT,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (actor_id),
    INDEX (created_at)
);

-- Add 2FA columns only if they don't already exist
SET @exist1 = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='isp_db' AND TABLE_NAME='users' AND COLUMN_NAME='twofa_secret');
SET @exist2 = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='isp_db' AND TABLE_NAME='users' AND COLUMN_NAME='twofa_enabled');

SET @sql1 = IF(@exist1 = 0, 'ALTER TABLE users ADD COLUMN twofa_secret VARCHAR(255) NULL', 'SELECT 1');
SET @sql2 = IF(@exist2 = 0, 'ALTER TABLE users ADD COLUMN twofa_enabled TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');

PREPARE stmt1 FROM @sql1; EXECUTE stmt1; DEALLOCATE PREPARE stmt1;
PREPARE stmt2 FROM @sql2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;

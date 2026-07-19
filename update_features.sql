USE isp_db;

-- Email verification + password reset tokens
SET @c1 = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='isp_db' AND TABLE_NAME='users' AND COLUMN_NAME='email_verified');
SET @c2 = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='isp_db' AND TABLE_NAME='users' AND COLUMN_NAME='verify_token');
SET @c3 = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='isp_db' AND TABLE_NAME='users' AND COLUMN_NAME='reset_token');
SET @c4 = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='isp_db' AND TABLE_NAME='users' AND COLUMN_NAME='reset_expires');

SET @s1 = IF(@c1=0,'ALTER TABLE users ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0','SELECT 1');
SET @s2 = IF(@c2=0,'ALTER TABLE users ADD COLUMN verify_token VARCHAR(64) NULL','SELECT 1');
SET @s3 = IF(@c3=0,'ALTER TABLE users ADD COLUMN reset_token VARCHAR(64) NULL','SELECT 1');
SET @s4 = IF(@c4=0,'ALTER TABLE users ADD COLUMN reset_expires DATETIME NULL','SELECT 1');

PREPARE st1 FROM @s1; EXECUTE st1; DEALLOCATE PREPARE st1;
PREPARE st2 FROM @s2; EXECUTE st2; DEALLOCATE PREPARE st2;
PREPARE st3 FROM @s3; EXECUTE st3; DEALLOCATE PREPARE st3;
PREPARE st4 FROM @s4; EXECUTE st4; DEALLOCATE PREPARE st4;

-- White-label / branding settings (single-row config)
CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY DEFAULT 1,
    brand_name VARCHAR(100) NOT NULL DEFAULT 'NetConnect ISP',
    brand_logo VARCHAR(255) DEFAULT '',
    primary_color VARCHAR(7) NOT NULL DEFAULT '#3b82f6',
    support_email VARCHAR(100) DEFAULT 'support@isp.com',
    currency VARCHAR(10) NOT NULL DEFAULT 'USD',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO settings (id, brand_name, primary_color, currency) VALUES (1, 'NetConnect ISP', '#3b82f6', 'USD')
    ON DUPLICATE KEY UPDATE id = 1;

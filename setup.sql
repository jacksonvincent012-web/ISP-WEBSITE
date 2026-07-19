CREATE DATABASE IF NOT EXISTS isp_db;
USE isp_db;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    phone_encrypted TEXT,
    address TEXT,
    address_encrypted TEXT,
    role ENUM('user','admin') DEFAULT 'user',
    status ENUM('active','suspended','pending') DEFAULT 'pending',
    email_verified TINYINT(1) DEFAULT 0,
    verify_token VARCHAR(100),
    reset_token VARCHAR(100),
    reset_expires DATETIME,
    twofa_enabled TINYINT(1) DEFAULT 0,
    twofa_secret VARCHAR(32) DEFAULT NULL,
    last_ip VARCHAR(45),
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    speed VARCHAR(50) NOT NULL,
    download_speed VARCHAR(50),
    upload_speed VARCHAR(50),
    price DECIMAL(10,2) NOT NULL,
    data_cap VARCHAR(50) DEFAULT 'Unlimited',
    duration_months INT DEFAULT 0,
    duration_hours INT NOT NULL DEFAULT 0,
    features TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active','expired','cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subscription_id INT,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) DEFAULT 'credit_card',
    status ENUM('pending','completed','failed','refunded') DEFAULT 'pending',
    transaction_ref VARCHAR(100),
    paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS usage_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    data_used_mb DECIMAL(10,2) DEFAULT 0,
    session_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    session_end TIMESTAMP NULL,
    ip_address VARCHAR(45),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subscription_id INT,
    invoice_no VARCHAR(50) NOT NULL UNIQUE,
    amount DECIMAL(10,2) NOT NULL,
    due_date DATE NOT NULL,
    status ENUM('unpaid','paid','overdue','cancelled') DEFAULT 'unpaid',
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS support_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
    status ENUM('open','in_progress','resolved','closed') DEFAULT 'open',
    admin_reply TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

INSERT INTO plans (name, speed, download_speed, upload_speed, price, data_cap, duration_months, duration_hours, features) VALUES
('2 Hours Pass', '10 Mbps', '10 Mbps', '2 Mbps', 10.00, 'Unlimited', 0, 2, 'Quick browse, Social media, Email'),
('3 Hours Pass', '15 Mbps', '15 Mbps', '3 Mbps', 15.00, 'Unlimited', 0, 3, 'Quick browse, Social media'),
('6 Hours Pass', '10 Mbps', '10 Mbps', '2 Mbps', 20.00, 'Unlimited', 0, 6, 'Half-day access, Social media, Streaming'),
('6 Hours Plus', '15 Mbps', '15 Mbps', '5 Mbps', 23.00, 'Unlimited', 0, 6, 'Extended browsing, Social media, Streaming'),
('12 Hours Pass', '15 Mbps', '15 Mbps', '5 Mbps', 30.00, 'Unlimited', 0, 12, 'Day browsing, Social media, Video calls'),
('1 Day Pass', '15 Mbps', '15 Mbps', '5 Mbps', 40.00, '2 GB', 0, 24, 'Daily browsing, Video calls, Social media'),
('2 Days Pass', '20 Mbps', '20 Mbps', '5 Mbps', 70.00, '3 GB', 0, 48, 'Weekend plan, HD streaming, Social media'),
('1 Week Pass', '20 Mbps', '20 Mbps', '5 Mbps', 200.00, '10 GB', 0, 168, 'Weekly browsing, HD streaming, Social media'),
('2 Weeks Pass', '25 Mbps', '25 Mbps', '10 Mbps', 350.00, '25 GB', 0, 336, 'Fortnight plan, HD streaming, Gaming'),
('1 Month Plan', '50 Mbps', '50 Mbps', '15 Mbps', 550.00, 'Unlimited', 1, 720, 'Full month, 4K streaming, Multiple devices'),
('Premium Monthly', '100 Mbps', '100 Mbps', '30 Mbps', 999.00, 'Unlimited', 1, 720, 'Ultra HD, VPN, Priority 24/7 support');

INSERT INTO users (username, email, password, full_name, phone, address, role, status, email_verified) VALUES
('admin', 'admin@isp.com', '$2y$10$bmhM35WTGAEG6BnDX/QKruDGUY0.Jeheeu8Z4q0OyR.8qUVGKoGqq', 'System Admin', '+254 110 869 425', 'Nairobi, Kenya', 'admin', 'active', 1),
('johndoe', 'john@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Doe', '+254 712 345 678', '123 Main St', 'user', 'active', 1),
('janedoe', 'jane@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane Doe', '+254 723 456 789', '456 Oak Ave', 'user', 'active', 1),
('honeytoken_admin', 'honeytoken@isp-system.internal', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'HONEYPOT ADMIN — DO NOT ACCESS', '+254700000000', 'Honeypot Address', 'admin', 'active', 1);

INSERT INTO subscriptions (user_id, plan_id, start_date, end_date, status) VALUES
(2, 3, '2026-01-01', '2026-07-01', 'active'),
(3, 4, '2026-03-01', '2026-09-01', 'active');

INSERT INTO payments (user_id, subscription_id, amount, payment_method, status, transaction_ref) VALUES
(2, 1, 34.99, 'credit_card', 'completed', 'TXN-001'),
(3, 2, 49.99, 'paypal', 'completed', 'TXN-002');

INSERT INTO invoices (user_id, subscription_id, invoice_no, amount, due_date, status) VALUES
(2, 1, 'INV-2026-0001', 34.99, '2026-07-01', 'paid'),
(3, 2, 'INV-2026-0002', 49.99, '2026-09-01', 'paid'),
(2, 1, 'INV-2026-0003', 34.99, '2026-08-01', 'unpaid');

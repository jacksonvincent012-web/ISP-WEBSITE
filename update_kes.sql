USE isp_db;

-- Replace plans with KES time-based bundles
TRUNCATE TABLE subscriptions;
TRUNCATE TABLE payments;
TRUNCATE TABLE invoices;

DELETE FROM plans;
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

-- Update branding
UPDATE settings SET currency = 'KES', brand_name = 'NetConnect KE' WHERE id = 1;

-- Set users as verified and active
UPDATE users SET email_verified = 1, status = 'active' WHERE email_verified = 0;

-- ============================================================
--  CyberShield Database — Updated Schema
--  Import this file in phpMyAdmin or run via MySQL CLI:
--    mysql -u root -p < cybershield_database.sql
-- ============================================================

-- Create database
CREATE DATABASE IF NOT EXISTS cybershield CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cybershield;

-- ─────────────────────────────────────────────────────────────
--  Drop existing tables (clean slate)
-- ─────────────────────────────────────────────────────────────
DROP TABLE IF EXISTS activity_log;
DROP TABLE IF EXISTS email_reports;
DROP TABLE IF EXISTS vendor_assessments;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS vendors;
DROP TABLE IF EXISTS users;

-- ─────────────────────────────────────────────────────────────
--  USERS
--  Passwords (bcrypt, cost 10):
--    admin  → Admin@123
--    seller → Seller@123
--    viewer → Viewer@123
-- ─────────────────────────────────────────────────────────────
CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email         VARCHAR(100) UNIQUE NOT NULL,
    full_name     VARCHAR(100) NOT NULL,
    store_name    VARCHAR(100) NOT NULL,
    role          ENUM('Admin','Seller','Viewer') DEFAULT 'Seller',
    is_active     BOOLEAN DEFAULT TRUE,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email    (email),
    INDEX idx_role     (role)
);

-- ─────────────────────────────────────────────────────────────
--  VENDORS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE vendors (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(255) NOT NULL,
    email          VARCHAR(100),
    industry       VARCHAR(100),
    contact_person VARCHAR(100),
    phone          VARCHAR(20),
    address        TEXT,
    store_name     VARCHAR(100),
    is_active      BOOLEAN DEFAULT TRUE,
    flagged        BOOLEAN DEFAULT FALSE,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name    (name),
    INDEX idx_email   (email),
    INDEX idx_flagged (flagged)
);

-- ─────────────────────────────────────────────────────────────
--  PRODUCTS  (NEW — powers the Seller Store page)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE products (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    name        VARCHAR(255)   NOT NULL,
    description TEXT,
    price       DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    stock       INT            NOT NULL DEFAULT 0,
    category    VARCHAR(100)   DEFAULT 'Other',
    status      ENUM('active','inactive') DEFAULT 'active',
    image_url   TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id  (user_id),
    INDEX idx_status   (status),
    INDEX idx_category (category)
);

-- ─────────────────────────────────────────────────────────────
--  VENDOR ASSESSMENTS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE vendor_assessments (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id        INT NOT NULL,
    score            DECIMAL(5,2) NOT NULL,
    rank             ENUM('A','B','C','D') NOT NULL,
    password_score   DECIMAL(5,2) NOT NULL,
    phishing_score   DECIMAL(5,2) NOT NULL,
    device_score     DECIMAL(5,2) NOT NULL,
    network_score    DECIMAL(5,2) NOT NULL,
    assessment_notes TEXT,
    assessed_by      INT,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id)   REFERENCES vendors(id) ON DELETE CASCADE,
    FOREIGN KEY (assessed_by) REFERENCES users(id)   ON DELETE SET NULL,
    INDEX idx_vendor_id  (vendor_id),
    INDEX idx_score      (score),
    INDEX idx_rank       (rank),
    INDEX idx_created_at (created_at)
);

-- ─────────────────────────────────────────────────────────────
--  ACTIVITY LOG
-- ─────────────────────────────────────────────────────────────
CREATE TABLE activity_log (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    user_id            INT,
    action_type        ENUM('login','logout','export','flag','refresh','alert','theme','profile','assessment','email') NOT NULL,
    action_description TEXT,
    ip_address         VARCHAR(45),
    user_agent         TEXT,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id    (user_id),
    INDEX idx_action_type (action_type),
    INDEX idx_created_at (created_at)
);

-- ─────────────────────────────────────────────────────────────
--  EMAIL REPORTS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE email_reports (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id        INT NOT NULL,
    recipient_email  VARCHAR(100) NOT NULL,
    subject          VARCHAR(255) NOT NULL,
    message          TEXT NOT NULL,
    additional_notes TEXT,
    sent_by          INT,
    status           ENUM('pending','sent','failed') DEFAULT 'pending',
    sent_at          TIMESTAMP NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    FOREIGN KEY (sent_by)   REFERENCES users(id)   ON DELETE SET NULL,
    INDEX idx_vendor_id (vendor_id),
    INDEX idx_status    (status),
    INDEX idx_sent_at   (sent_at)
);

-- ─────────────────────────────────────────────────────────────
--  SEED: USERS
--  Passwords (bcrypt, cost 10):
--    admin  → Admin@123
--    seller → Seller@123
--    viewer → Viewer@123
-- ─────────────────────────────────────────────────────────────
INSERT INTO users (username, password_hash, email, full_name, store_name, role) VALUES
('admin',
 '$2y$10$BUZrq5cvZo7XL7063kWc2uM8P1j2X0kvNHQ/QTpVNUAuV7q7WiRfO',
 'admin@cybershield.ph', 'System Administrator', 'CyberShield Admin', 'Admin'),

('seller',
 '$2y$10$sj2SICvEmuRT3vhsa9IskO6dc3gFmfMYuSN.U/MYtUjNJtCjt4/.q',
 'seller@demo.ph', 'Demo Seller', 'Demo Store', 'Seller'),

('viewer',
 '$2y$10$VP6fVllWeLjea6OYxMNhMOYgrSmJX04yazqQFX8u0KY4.ZGjvG98a',
 'viewer@demo.ph', 'Demo Viewer', 'Demo Company', 'Viewer');

-- ─────────────────────────────────────────────────────────────
--  NOTE: If the above hashes don't work with your PHP version,
--  run this PHP snippet to generate fresh ones and UPDATE users:
--
--  <?php
--  echo password_hash('Admin@123',  PASSWORD_BCRYPT) . "\n";
--  echo password_hash('Seller@123', PASSWORD_BCRYPT) . "\n";
--  echo password_hash('Viewer@123', PASSWORD_BCRYPT) . "\n";
--
--  Then:
--  UPDATE users SET password_hash='<new_hash>' WHERE username='admin';
--  UPDATE users SET password_hash='<new_hash>' WHERE username='seller';
--  UPDATE users SET password_hash='<new_hash>' WHERE username='viewer';
-- ─────────────────────────────────────────────────────────────

-- ─────────────────────────────────────────────────────────────
--  SEED: VENDORS
-- ─────────────────────────────────────────────────────────────
INSERT INTO vendors (name, email, industry, contact_person, phone, store_name) VALUES
('TechCorp Solutions',   'security@techcorp.com',      'Technology',       'John Smith',      '+63-2-555-0123', 'TechCorp Store'),
('SecureNet Inc',        'info@securenet.com',          'Network Security', 'Sarah Johnson',   '+63-2-555-0124', 'SecureNet Shop'),
('DataSafe Systems',     'contact@datasafe.com',        'Data Management',  'Michael Brown',   '+63-2-555-0125', 'DataSafe Hub'),
('CloudGuard Services',  'admin@cloudguard.com',        'Cloud Services',   'Emily Davis',     '+63-2-555-0126', 'CloudGuard PH'),
('CyberShield Pro',      'support@cybershieldpro.com',  'Cybersecurity',    'David Wilson',    '+63-2-555-0127', 'CS Pro Store'),
('RiskAway Ltd',         'hello@riskaway.com',          'Risk Management',  'Lisa Anderson',   '+63-2-555-0128', 'RiskAway Shop'),
('SafeHarbor Tech',      'info@safeharbor.com',         'IT Services',      'James Taylor',    '+63-2-555-0129', 'SafeHarbor PH'),
('DefensePoint Systems', 'security@defensepoint.com',   'Defense Systems',  'Robert Martinez', '+63-2-555-0130', 'DefensePoint');

-- ─────────────────────────────────────────────────────────────
--  SEED: VENDOR ASSESSMENTS
-- ─────────────────────────────────────────────────────────────
INSERT INTO vendor_assessments
    (vendor_id, score, rank, password_score, phishing_score, device_score, network_score, assessment_notes, assessed_by)
VALUES
(1, 85.50, 'B', 90.00, 85.00, 80.00, 87.00, 'Good overall security with minor improvements needed', 1),
(2, 92.75, 'A', 95.00, 90.00, 93.00, 93.00, 'Excellent security posture across all categories', 1),
(3, 78.25, 'C', 75.00, 80.00, 77.00, 81.00, 'Needs improvement in password policies', 1),
(4, 88.00, 'B', 85.00, 90.00, 88.00, 89.00, 'Strong security with room for optimization', 1),
(5, 95.50, 'A', 98.00, 95.00, 94.00, 95.00, 'Outstanding security implementation', 1),
(6, 71.50, 'C', 70.00, 72.00, 73.00, 71.00, 'Requires immediate attention to security gaps', 1),
(7, 83.75, 'B', 88.00, 82.00, 84.00, 81.00, 'Good security framework with specific areas for enhancement', 1),
(8, 65.25, 'D', 60.00, 68.00, 67.00, 66.00, 'Critical security vulnerabilities identified', 1);

-- ─────────────────────────────────────────────────────────────
--  SEED: SAMPLE PRODUCTS (for Demo Seller)
-- ─────────────────────────────────────────────────────────────
INSERT INTO products (user_id, name, description, price, stock, category, status, image_url) VALUES
(2, 'Wireless Noise-Cancelling Headphones',
   'Premium audio with 30-hour battery life and active noise cancellation.',
   3499.00, 25, 'Electronics', 'active', ''),

(2, 'Mechanical Gaming Keyboard',
   'RGB backlit keyboard with Cherry MX switches for precise input.',
   2299.00, 40, 'Electronics', 'active', ''),

(2, 'Ergonomic Office Chair',
   'Lumbar support mesh chair with adjustable armrests and height.',
   8999.00, 8, 'Home & Garden', 'active', ''),

(2, 'USB-C Hub 7-in-1',
   'Expands your laptop with HDMI, 3 USB-A, SD card, and 100W PD.',
   1199.00, 60, 'Electronics', 'active', ''),

(2, 'Protein Powder – Chocolate',
   '2kg whey protein blend with 25g protein per serving.',
   1850.00, 0, 'Health & Beauty', 'inactive', '');

-- ─────────────────────────────────────────────────────────────
--  SEED: ACTIVITY LOG
-- ─────────────────────────────────────────────────────────────
INSERT INTO activity_log (user_id, action_type, action_description, ip_address) VALUES
(1, 'login',      'Administrator logged in',                      '127.0.0.1'),
(1, 'assessment', 'Completed assessment for TechCorp Solutions',  '127.0.0.1'),
(1, 'export',     'Exported vendor assessment data to CSV',        '127.0.0.1'),
(1, 'flag',       'Flagged RiskAway Ltd for review',              '127.0.0.1'),
(2, 'login',      'Demo Seller logged in',                        '192.168.1.100'),
(2, 'assessment', 'Completed assessment for SecureNet Inc',       '192.168.1.100');

-- ─────────────────────────────────────────────────────────────
--  SEED: EMAIL REPORTS
-- ─────────────────────────────────────────────────────────────
INSERT INTO email_reports
    (vendor_id, recipient_email, subject, message, additional_notes, sent_by, status, sent_at)
VALUES
(3, 'contact@datasafe.com',
   'CyberShield Risk Assessment Report',
   'Your security assessment results are now available.',
   'Please review and address identified concerns.',
   1, 'sent', NOW()),

(6, 'hello@riskaway.com',
   'Urgent: Security Assessment Results',
   'Critical security issues detected in your assessment.',
   'Immediate action required. Please contact us for remediation.',
   1, 'sent', NOW());

-- ─────────────────────────────────────────────────────────────
--  VIEWS
-- ─────────────────────────────────────────────────────────────

-- Latest assessment per vendor
CREATE OR REPLACE VIEW vendor_latest_assessments AS
SELECT
    v.id            AS vendor_id,
    v.name          AS vendor_name,
    v.email         AS vendor_email,
    v.store_name    AS store_name,
    v.industry,
    v.contact_person,
    v.flagged,
    va.id           AS assessment_id,
    va.score,
    va.rank,
    va.password_score,
    va.phishing_score,
    va.device_score,
    va.network_score,
    va.created_at   AS last_assessment_date
FROM vendors v
LEFT JOIN vendor_assessments va  ON v.id = va.vendor_id
LEFT JOIN vendor_assessments va2 ON v.id = va2.vendor_id AND va.created_at < va2.created_at
WHERE va2.id IS NULL;

-- Risk summary by rank
CREATE OR REPLACE VIEW risk_summary AS
SELECT
    rank,
    COUNT(*)              AS count,
    AVG(score)            AS avg_score,
    AVG(password_score)   AS avg_password,
    AVG(phishing_score)   AS avg_phishing,
    AVG(device_score)     AS avg_device,
    AVG(network_score)    AS avg_network
FROM vendor_assessments
GROUP BY rank;

-- Product inventory summary per user
CREATE OR REPLACE VIEW seller_inventory_summary AS
SELECT
    u.id                               AS user_id,
    u.full_name,
    u.store_name,
    COUNT(p.id)                        AS total_products,
    SUM(p.status = 'active')           AS active_products,
    SUM(p.stock = 0)                   AS out_of_stock,
    SUM(p.price * p.stock)             AS total_inventory_value
FROM users u
LEFT JOIN products p ON p.user_id = u.id
GROUP BY u.id, u.full_name, u.store_name;

-- ─────────────────────────────────────────────────────────────
--  BADGES & ACHIEVEMENTS SYSTEM
-- ─────────────────────────────────────────────────────────────

-- Badges table
CREATE TABLE IF NOT EXISTS badges (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    description     TEXT NOT NULL,
    icon            VARCHAR(50) NOT NULL,
    color           VARCHAR(7) DEFAULT '#3B8BFF',
    category        ENUM('assessment','consistency','improvement','milestone','special') NOT NULL,
    requirement_type ENUM('score','count','rank','streak','special') NOT NULL,
    requirement_value INT NOT NULL,
    points          INT DEFAULT 10,
    is_active       BOOLEAN DEFAULT TRUE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_requirement (requirement_type, requirement_value)
);

-- User achievements table
CREATE TABLE IF NOT EXISTS user_achievements (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    badge_id        INT NOT NULL,
    earned_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assessment_id   INT NULL,
    points_earned   INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE,
    FOREIGN KEY (assessment_id) REFERENCES vendor_assessments(id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_badge (user_id, badge_id),
    INDEX idx_user_id (user_id),
    INDEX idx_badge_id (badge_id),
    INDEX idx_earned_at (earned_at)
);

-- Seed badges data
INSERT INTO badges (name, description, icon, color, category, requirement_type, requirement_value, points) VALUES
('Security Elite', 'Achieve a perfect score (100%) on any assessment', '🏆', '#f5c518', 'assessment', 'score', 100, 50),
('Security Master', 'Score 90% or higher on any assessment', '🥇', '#f5c518', 'assessment', 'score', 90, 40),
('Security Expert', 'Score 80% or higher on any assessment', '🥈', '#c0c0c0', 'assessment', 'score', 80, 30),
('Security Pro', 'Score 70% or higher on any assessment', '🥉', '#cd7f32', 'assessment', 'score', 70, 20),
('Low Risk Hero', 'Achieve Rank A on any assessment', '✅', '#10d982', 'assessment', 'rank', 1, 35),
('Risk Manager', 'Achieve Rank B on any assessment', '📈', '#3b8bff', 'assessment', 'rank', 2, 25),
('Risk Aware', 'Achieve Rank C on any assessment', '⚠️', '#f5b731', 'assessment', 'rank', 3, 15),
('Consistent Learner', 'Complete 5 assessments', '📚', '#4090ff', 'consistency', 'count', 5, 20),
('Dedicated Defender', 'Complete 10 assessments', '🔒', '#a855f7', 'consistency', 'count', 10, 35),
('Security Veteran', 'Complete 25 assessments', '🛡️', '#ef4444', 'consistency', 'count', 25, 50),
('Assessment Master', 'Complete 50 assessments', '👑', '#f5c518', 'consistency', 'count', 50, 75),
('Quick Learner', 'Improve score by 20% in next assessment', '📈', '#10d982', 'improvement', 'special', 20, 30),
('Rise and Shine', 'Improve from Rank D to Rank B or better', '☀️', '#f5b731', 'improvement', 'special', 1, 40),
('First Steps', 'Complete your first assessment', '🎯', '#3b8bff', 'milestone', 'count', 1, 10),
('Week Warrior', 'Complete 3 assessments in one week', '⚡', '#ff8c42', 'milestone', 'special', 3, 25),
('Monthly Champion', 'Complete 10 assessments in one month', '🏅', '#10d982', 'milestone', 'special', 10, 40),
('Password Protector', 'Score 90% or higher in password security', '🔐', '#3b8bff', 'assessment', 'special', 90, 20),
('Phishing Fighter', 'Score 90% or higher in phishing awareness', '🎣', '#ff8c42', 'assessment', 'special', 90, 20),
('Device Guardian', 'Score 90% or higher in device security', '💻', '#10d982', 'assessment', 'special', 90, 20),
('Network Defender', 'Score 90% or higher in network security', '🌐', '#7b72f0', 'assessment', 'special', 90, 20),
('Password Perfect', 'Score 100% in password security', '🔑', '#f5c518', 'assessment', 'special', 100, 25),
('Phishing Perfect', 'Score 100% in phishing awareness', '📧', '#f5c518', 'assessment', 'special', 100, 25),
('Device Perfect', 'Score 100% in device security', '🖥️', '#f5c518', 'assessment', 'special', 100, 25),
('Network Perfect', 'Score 100% in network security', '🔌', '#f5c518', 'assessment', 'special', 100, 25);

-- Badge statistics views
CREATE OR REPLACE VIEW user_badge_summary AS
SELECT 
    u.id as user_id,
    u.full_name,
    u.email,
    COUNT(ua.id) as total_badges_earned,
    SUM(b.points) as total_points,
    COUNT(CASE WHEN b.category = 'assessment' THEN 1 END) as assessment_badges,
    COUNT(CASE WHEN b.category = 'consistency' THEN 1 END) as consistency_badges,
    COUNT(CASE WHEN b.category = 'improvement' THEN 1 END) as improvement_badges,
    COUNT(CASE WHEN b.category = 'milestone' THEN 1 END) as milestone_badges,
    COUNT(CASE WHEN b.category = 'special' THEN 1 END) as special_badges,
    MAX(ua.earned_at) as last_earned_at
FROM users u
LEFT JOIN user_achievements ua ON u.id = ua.user_id
LEFT JOIN badges b ON ua.badge_id = b.id
GROUP BY u.id, u.full_name, u.email;

CREATE OR REPLACE VIEW badge_leaderboard AS
SELECT 
    u.id as user_id,
    u.full_name,
    u.store_name,
    COUNT(ua.id) as total_badges,
    SUM(b.points) as total_points,
    RANK() OVER (ORDER BY SUM(b.points) DESC, COUNT(ua.id) DESC) as rank_position
FROM users u
LEFT JOIN user_achievements ua ON u.id = ua.user_id
LEFT JOIN badges b ON ua.badge_id = b.id
GROUP BY u.id, u.full_name, u.store_name
ORDER BY total_points DESC, total_badges DESC;

-- ─────────────────────────────────────────────────────────────
--  QUICK VERIFICATION SELECTS
-- ─────────────────────────────────────────────────────────────
SELECT 'users'              AS `table`, COUNT(*) AS `rows` FROM users
UNION ALL
SELECT 'vendors',                       COUNT(*)           FROM vendors
UNION ALL
SELECT 'vendor_assessments',            COUNT(*)           FROM vendor_assessments
UNION ALL
SELECT 'products',                      COUNT(*)           FROM products
UNION ALL
SELECT 'activity_log',                  COUNT(*)           FROM activity_log
UNION ALL
SELECT 'email_reports',                 COUNT(*)           FROM email_reports
UNION ALL
SELECT 'badges',                        COUNT(*)           FROM badges
UNION ALL
SELECT 'user_achievements',             COUNT(*)           FROM user_achievements;
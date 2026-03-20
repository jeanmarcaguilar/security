-- CyberShield Database Setup
-- Import this file in phpMyAdmin

-- Create database
CREATE DATABASE IF NOT EXISTS cybershield CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Use the database
USE cybershield;

-- Drop existing tables if they exist (to fix schema issues)
DROP TABLE IF EXISTS activity_log;
DROP TABLE IF EXISTS email_reports;
DROP TABLE IF EXISTS vendor_assessments;
DROP TABLE IF EXISTS vendors;
DROP TABLE IF EXISTS users;

-- Create users table with correct schema
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    store_name VARCHAR(100) NOT NULL,
    role ENUM('Admin', 'Seller', 'Viewer') DEFAULT 'Seller',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role)
);

-- Create vendors table
CREATE TABLE vendors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    industry VARCHAR(100),
    contact_person VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    flagged BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_email (email),
    INDEX idx_flagged (flagged)
);

-- Create vendor_assessments table
CREATE TABLE vendor_assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    score DECIMAL(5,2) NOT NULL,
    rank ENUM('A', 'B', 'C', 'D') NOT NULL,
    password_score DECIMAL(5,2) NOT NULL,
    phishing_score DECIMAL(5,2) NOT NULL,
    device_score DECIMAL(5,2) NOT NULL,
    network_score DECIMAL(5,2) NOT NULL,
    assessment_notes TEXT,
    assessed_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    FOREIGN KEY (assessed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_vendor_id (vendor_id),
    INDEX idx_score (score),
    INDEX idx_rank (rank),
    INDEX idx_created_at (created_at)
);

-- Create activity_log table
CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action_type ENUM('login', 'logout', 'export', 'flag', 'refresh', 'alert', 'theme', 'profile', 'assessment', 'email') NOT NULL,
    action_description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action_type (action_type),
    INDEX idx_created_at (created_at)
);

-- Create email_reports table
CREATE TABLE email_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    recipient_email VARCHAR(100) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    additional_notes TEXT,
    sent_by INT,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    FOREIGN KEY (sent_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_vendor_id (vendor_id),
    INDEX idx_status (status),
    INDEX idx_sent_at (sent_at)
);

-- Insert default users with properly hashed passwords
INSERT INTO users (username, password_hash, email, full_name, store_name, role) VALUES 
('admin', '$2y$10$eGH7nLWmhmRWHytey4FVSebkRjurzTFZK5WNVNG/2Zz7splT05jjC', 'admin@cybershield.ph', 'System Administrator', 'CyberShield Admin', 'Admin'),
('seller', '$2y$10$BMmZ7wkvDtUqsJE9Pvlmee2lD3tPgwSS3w04mDU/DhpJRHOX0O/0i', 'seller@demo.ph', 'Demo Seller', 'Demo Store', 'Seller'),
('viewer', '$2y$10$QPHOvzXE/S.1cPIxlPVYEuGckC5JFEdN3LF8F61.moiVBVApLKwZm', 'viewer@demo.ph', 'Demo Viewer', 'Demo Company', 'Viewer');

-- Insert sample vendors
INSERT INTO vendors (name, email, industry, contact_person, phone) VALUES 
('TechCorp Solutions', 'security@techcorp.com', 'Technology', 'John Smith', '+1-555-0123'),
('SecureNet Inc', 'info@securenet.com', 'Network Security', 'Sarah Johnson', '+1-555-0124'),
('DataSafe Systems', 'contact@datasafe.com', 'Data Management', 'Michael Brown', '+1-555-0125'),
('CloudGuard Services', 'admin@cloudguard.com', 'Cloud Services', 'Emily Davis', '+1-555-0126'),
('CyberShield Pro', 'support@cybershieldpro.com', 'Cybersecurity', 'David Wilson', '+1-555-0127'),
('RiskAway Ltd', 'hello@riskaway.com', 'Risk Management', 'Lisa Anderson', '+1-555-0128'),
('SafeHarbor Tech', 'info@safeharbor.com', 'IT Services', 'James Taylor', '+1-555-0129'),
('DefensePoint Systems', 'security@defensepoint.com', 'Defense Systems', 'Robert Martinez', '+1-555-0130');

-- Insert sample vendor assessments
INSERT INTO vendor_assessments (vendor_id, score, rank, password_score, phishing_score, device_score, network_score, assessment_notes, assessed_by) VALUES 
(1, 85.50, 'B', 90.00, 85.00, 80.00, 87.00, 'Good overall security with minor improvements needed', 1),
(2, 92.75, 'A', 95.00, 90.00, 93.00, 93.00, 'Excellent security posture across all categories', 1),
(3, 78.25, 'C', 75.00, 80.00, 77.00, 81.00, 'Needs improvement in password policies', 1),
(4, 88.00, 'B', 85.00, 90.00, 88.00, 89.00, 'Strong security with room for optimization', 1),
(5, 95.50, 'A', 98.00, 95.00, 94.00, 95.00, 'Outstanding security implementation', 1),
(6, 71.50, 'C', 70.00, 72.00, 73.00, 71.00, 'Requires immediate attention to security gaps', 1),
(7, 83.75, 'B', 88.00, 82.00, 84.00, 81.00, 'Good security framework with specific areas for enhancement', 1),
(8, 65.25, 'D', 60.00, 68.00, 67.00, 66.00, 'Critical security vulnerabilities identified', 1);

-- Insert sample activity logs
INSERT INTO activity_log (user_id, action_type, action_description, ip_address) VALUES 
(1, 'login', 'Administrator logged into dashboard', '127.0.0.1'),
(1, 'assessment', 'Completed assessment for TechCorp Solutions', '127.0.0.1'),
(1, 'export', 'Exported vendor assessment data to CSV', '127.0.0.1'),
(1, 'flag', 'Flagged RiskAway Ltd for review', '127.0.0.1'),
(2, 'login', 'Demo Seller logged into dashboard', '192.168.1.100'),
(2, 'assessment', 'Completed assessment for SecureNet Inc', '192.168.1.100');

-- Insert sample email reports
INSERT INTO email_reports (vendor_id, recipient_email, subject, message, additional_notes, sent_by, status, sent_at) VALUES 
(3, 'contact@datasafe.com', 'CyberShield Risk Assessment Report', 'Your security assessment results are now available.', 'Please review the attached assessment and address identified concerns.', 1, 'sent', NOW()),
(6, 'hello@riskaway.com', 'Urgent: Security Assessment Results', 'Critical security issues detected in your assessment.', 'Immediate action required. Please contact us for remediation assistance.', 1, 'sent', NOW());

-- Create views for common queries
CREATE VIEW vendor_latest_assessments AS
SELECT 
    v.id as vendor_id,
    v.name as vendor_name,
    v.email as vendor_email,
    v.industry,
    v.contact_person,
    v.flagged,
    va.score,
    va.rank,
    va.password_score,
    va.phishing_score,
    va.device_score,
    va.network_score,
    va.created_at as last_assessment_date
FROM vendors v
LEFT JOIN vendor_assessments va ON v.id = va.vendor_id
LEFT JOIN vendor_assessments va2 ON v.id = va2.vendor_id AND va.created_at < va2.created_at
WHERE va2.id IS NULL;

CREATE VIEW risk_summary AS
SELECT 
    rank,
    COUNT(*) as count,
    AVG(score) as avg_score,
    AVG(password_score) as avg_password,
    AVG(phishing_score) as avg_phishing,
    AVG(device_score) as avg_device,
    AVG(network_score) as avg_network
FROM vendor_assessments
GROUP BY rank;

-- Show the created data
SELECT 'Users:' as table_name;
SELECT * FROM users;

SELECT 'Vendors:' as table_name;
SELECT * FROM vendors;

SELECT 'Vendor Assessments:' as table_name;
SELECT * FROM vendor_assessments;

SELECT 'Activity Log:' as table_name;
SELECT * FROM activity_log;

SELECT 'Email Reports:' as table_name;
SELECT * FROM email_reports;

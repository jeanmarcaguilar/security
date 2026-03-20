-- CyberShield Database Setup
-- Import this file in phpMyAdmin

-- Create database
CREATE DATABASE IF NOT EXISTS cybershield CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Use the database
USE cybershield;

-- Drop existing table if it exists (to fix schema issues)
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

-- Insert default users with properly hashed passwords
INSERT INTO users (username, password_hash, email, full_name, store_name, role) VALUES 
('admin', '$2y$10$eGH7nLWmhmRWHytey4FVSebkRjurzTFZK5WNVNG/2Zz7splT05jjC', 'admin@cybershield.ph', 'System Administrator', 'CyberShield Admin', 'Admin'),
('seller', '$2y$10$BMmZ7wkvDtUqsJE9Pvlmee2lD3tPgwSS3w04mDU/DhpJRHOX0O/0i', 'seller@demo.ph', 'Demo Seller', 'Demo Store', 'Seller'),
('viewer', '$2y$10$QPHOvzXE/S.1cPIxlPVYEuGckC5JFEdN3LF8F61.moiVBVApLKwZm', 'viewer@demo.ph', 'Demo Viewer', 'Demo Company', 'Viewer');

-- Show the created users
SELECT * FROM users;

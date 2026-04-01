-- Apply this script to fix the missing columns in assessment_answers table
-- Run this in phpMyAdmin or your MySQL client

USE cybershield;

-- Add missing columns to assessment_answers table
ALTER TABLE assessment_answers 
ADD COLUMN question_text TEXT NOT NULL DEFAULT '' AFTER question_id,
ADD COLUMN correct_answer VARCHAR(255) NOT NULL DEFAULT '' AFTER user_answer,
ADD COLUMN category VARCHAR(50) NOT NULL DEFAULT 'general' AFTER is_correct;

-- Add index for the new category column
ALTER TABLE assessment_answers 
ADD INDEX idx_category (category);

-- Migration: Add institutional_id field to users table
-- Run this SQL script to update your database

USE imrs_db;

-- Add institutional_id column (if it doesn't exist)
ALTER TABLE users ADD COLUMN institutional_id VARCHAR(50) NULL AFTER email;

-- Update role enum to include General_User (if not already included)
ALTER TABLE users MODIFY COLUMN role ENUM('Student', 'Staff', 'Proctor', 'Office_Head', 'Maintenance_Team', 'Maintenance_Manager', 'Admin', 'General_User') NOT NULL;

-- Add index for faster lookups (if it doesn't exist)
CREATE INDEX idx_institutional_id ON users(institutional_id);


-- Simple Migration: Add institutional_id field
-- Make sure you select the imrs_db database first in phpMyAdmin

ALTER TABLE users ADD COLUMN institutional_id VARCHAR(50) NULL AFTER email;

ALTER TABLE users MODIFY COLUMN role ENUM('Student', 'Staff', 'Proctor', 'Office_Head', 'Maintenance_Team', 'Maintenance_Manager', 'Admin', 'General_User') NOT NULL;

CREATE INDEX idx_institutional_id ON users(institutional_id);




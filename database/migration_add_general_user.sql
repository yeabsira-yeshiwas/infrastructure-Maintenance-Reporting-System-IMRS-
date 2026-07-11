-- Migration: Add General_User role and support for unregistered users
-- Run this SQL script to update your database

USE imrs_db;

-- Add General_User to the role ENUM
ALTER TABLE users MODIFY COLUMN role ENUM('Student', 'Staff', 'Proctor', 'Office_Head', 'Maintenance_Team', 'Maintenance_Manager', 'Admin', 'General_User') NOT NULL;

-- Modify maintenance_requests to allow NULL user_id for unregistered users
ALTER TABLE maintenance_requests MODIFY COLUMN user_id INT NULL;

-- Add email field for unregistered users
ALTER TABLE maintenance_requests ADD COLUMN submitter_email VARCHAR(100) NULL AFTER user_id;
ALTER TABLE maintenance_requests ADD COLUMN submitter_name VARCHAR(100) NULL AFTER submitter_email;

-- Update foreign key constraint to allow NULL
ALTER TABLE maintenance_requests DROP FOREIGN KEY maintenance_requests_ibfk_1;
ALTER TABLE maintenance_requests ADD CONSTRAINT maintenance_requests_ibfk_1 
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE;

-- Update status enum to include 'Direct_To_Manager' or we can use 'Approved' for General User requests
-- Actually, we'll use 'Approved' status for General User requests that skip approval




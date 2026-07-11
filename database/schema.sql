/*
 * Infrastructure Maintenance Reporting System (IMRS) Database Schema
 * Bahir Dar Institute of Technology (BIT)
 */

CREATE DATABASE IF NOT EXISTS imrs_db;
USE imrs_db;

-- Users table with role-based access
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    institutional_id VARCHAR(50) NULL,
    role ENUM('Student', 'Staff', 'Proctor', 'Office_Head', 'Maintenance_Team', 'Maintenance_Manager', 'Admin', 'General_User') NOT NULL,
    department VARCHAR(100),
    phone VARCHAR(20),
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Infrastructure types
CREATE TABLE IF NOT EXISTS infrastructure_types (
    type_id INT AUTO_INCREMENT PRIMARY KEY,
    type_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Locations
CREATE TABLE IF NOT EXISTS locations (
    location_id INT AUTO_INCREMENT PRIMARY KEY,
    location_name VARCHAR(100) NOT NULL,
    building VARCHAR(100),
    floor VARCHAR(50),
    room_number VARCHAR(50),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Proctor ↔ student-area location (manager assigns when creating a Proctor user)
CREATE TABLE IF NOT EXISTS proctor_locations (
    proctor_user_id INT NOT NULL,
    location_name VARCHAR(150) NOT NULL,
    PRIMARY KEY (proctor_user_id, location_name),
    FOREIGN KEY (proctor_user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Maintenance requests
CREATE TABLE IF NOT EXISTS maintenance_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    request_number VARCHAR(20) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    infrastructure_type_id INT NOT NULL,
    location_id INT NOT NULL,
    assigned_office_head_id INT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    priority ENUM('Low', 'Medium', 'High', 'Urgent') DEFAULT 'Medium',
    status ENUM('Pending', 'Approved', 'Rejected', 'Assigned', 'In_Progress', 'Completed', 'Closed') DEFAULT 'Pending',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL,
    assigned_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    closed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (infrastructure_type_id) REFERENCES infrastructure_types(type_id),
    FOREIGN KEY (location_id) REFERENCES locations(location_id),
    FOREIGN KEY (assigned_office_head_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Request attachments (images/files)
CREATE TABLE IF NOT EXISTS request_attachments (
    attachment_id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(50),
    file_size INT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES maintenance_requests(request_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Approval records
CREATE TABLE IF NOT EXISTS approvals (
    approval_id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    approver_id INT NOT NULL,
    approver_role ENUM('Proctor', 'Office_Head') NOT NULL,
    decision ENUM('Approved', 'Rejected') NOT NULL,
    feedback TEXT,
    approved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES maintenance_requests(request_id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Task assignments
CREATE TABLE IF NOT EXISTS task_assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    assigned_by INT NOT NULL,
    assigned_to INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    scheduled_date DATE,
    notes TEXT,
    FOREIGN KEY (request_id) REFERENCES maintenance_requests(request_id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Status updates and progress tracking
CREATE TABLE IF NOT EXISTS status_updates (
    update_id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    updated_by INT NOT NULL,
    old_status VARCHAR(50),
    new_status VARCHAR(50) NOT NULL,
    update_message TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES maintenance_requests(request_id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Notifications
CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    request_id INT,
    notification_type VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (request_id) REFERENCES maintenance_requests(request_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- System logs for audit trail
CREATE TABLE IF NOT EXISTS system_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default infrastructure types
INSERT INTO infrastructure_types (type_name, description) VALUES
('Electrical', 'Electrical systems, wiring, outlets, lighting'),
('Plumbing', 'Water supply, drainage, pipes, fixtures'),
('HVAC', 'Heating, ventilation, and air conditioning systems'),
('Furniture', 'Desks, chairs, cabinets, tables'),
('Building Structure', 'Walls, doors, windows, ceilings, floors'),
('IT Equipment', 'Computers, network equipment, printers'),
('Safety Equipment', 'Fire extinguishers, emergency exits, alarms'),
('Other', 'Other maintenance issues');

-- Insert default locations
INSERT INTO locations (location_name, building, floor, room_number) VALUES
('Main Building - Ground Floor', 'Main Building', 'Ground', '101'),
('Main Building - Ground Floor', 'Main Building', 'Ground', '102'),
('Main Building - First Floor', 'Main Building', 'First', '201'),
('Main Building - First Floor', 'Main Building', 'First', '202'),
('Engineering Block', 'Engineering Building', 'Ground', 'E101'),
('Engineering Block', 'Engineering Building', 'First', 'E201'),
('Library', 'Library Building', 'Ground', 'L001'),
('Laboratory Block', 'Lab Building', 'Ground', 'LAB01'),
('Administration Block', 'Admin Building', 'Ground', 'ADM01'),
('Cafeteria', 'Cafeteria Building', 'Ground', 'CAF01');

-- Create indexes for better performance
CREATE INDEX idx_request_status ON maintenance_requests(status);
CREATE INDEX idx_request_user ON maintenance_requests(user_id);
CREATE INDEX idx_request_type ON maintenance_requests(infrastructure_type_id);
CREATE INDEX idx_notification_user ON notifications(user_id, is_read);
CREATE INDEX idx_log_user ON system_logs(user_id);


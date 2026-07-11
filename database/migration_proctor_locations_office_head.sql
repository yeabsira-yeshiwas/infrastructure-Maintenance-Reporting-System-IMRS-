-- Proctor ↔ location assignments (Maintenance Manager sets these when creating a Proctor)
-- Student request location must match a row here for that proctor to see/approve it.
-- Staff requests store assigned_office_head_id (chosen from active Office Heads on the form).

USE imrs_db;

CREATE TABLE IF NOT EXISTS proctor_locations (
    proctor_user_id INT NOT NULL,
    location_name VARCHAR(150) NOT NULL,
    PRIMARY KEY (proctor_user_id, location_name),
    CONSTRAINT fk_proctor_locations_user FOREIGN KEY (proctor_user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Run once. If the column already exists, skip this line or remove the duplicate error.
ALTER TABLE maintenance_requests
    ADD COLUMN assigned_office_head_id INT NULL AFTER location_id;

ALTER TABLE maintenance_requests
    ADD CONSTRAINT fk_mr_assigned_office_head FOREIGN KEY (assigned_office_head_id) REFERENCES users(user_id) ON DELETE SET NULL;

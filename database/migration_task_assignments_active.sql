-- Migration: Add is_active column to task_assignments to mark current assignment
ALTER TABLE task_assignments ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1;

-- Backfill existing rows to active (already defaulted to 1)
UPDATE task_assignments SET is_active = 1 WHERE is_active IS NULL;

-- Run this in phpMyAdmin ONLY if the site could not add the columns by itself.
-- (If a line says the column already exists, that's fine - skip it.)
ALTER TABLE projects   ADD COLUMN service_id INT NULL DEFAULT NULL;
ALTER TABLE experience ADD COLUMN service_id INT NULL DEFAULT NULL;

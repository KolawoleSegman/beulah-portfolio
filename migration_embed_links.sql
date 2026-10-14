-- Only needed for databases imported from an older version.
-- (The admin also applies this automatically the first time you open a project form.)
ALTER TABLE project_media MODIFY media_type ENUM('image','video','embed') NOT NULL;

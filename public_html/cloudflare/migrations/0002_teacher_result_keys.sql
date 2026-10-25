ALTER TABLE erp_marks ADD COLUMN submission_key VARCHAR(36) NULL;
ALTER TABLE erp_marks ADD COLUMN submission_hash VARCHAR(64) NULL;
CREATE UNIQUE INDEX erp_marks_submission_key_unique ON erp_marks (submission_key);
INSERT INTO migrations(migration,batch) SELECT '2026_10_25_130000_add_teacher_submission_key', COALESCE(MAX(batch),0)+1 FROM migrations;

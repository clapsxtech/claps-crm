ALTER TABLE erp_teacher_candidates ADD COLUMN merged_into_id INTEGER;
ALTER TABLE erp_teacher_candidates ADD COLUMN reviewed_at DATETIME;
ALTER TABLE erp_teacher_candidates ADD COLUMN reviewed_by INTEGER;
CREATE INDEX erp_teacher_candidates_merged_into_id_index ON erp_teacher_candidates(merged_into_id);
CREATE TABLE erp_teacher_matching_profiles (
 id INTEGER PRIMARY KEY AUTOINCREMENT, teacher_id INTEGER NOT NULL UNIQUE,
 mediums TEXT NOT NULL, verified_by INTEGER NOT NULL, verified_at DATETIME NOT NULL,
 verification_note TEXT NOT NULL, created_at DATETIME, updated_at DATETIME
);
ALTER TABLE erp_demos ADD COLUMN starts_at DATETIME;
ALTER TABLE erp_demos ADD COLUMN ends_at DATETIME;
ALTER TABLE erp_demos ADD COLUMN timezone VARCHAR(255);
ALTER TABLE erp_demos ADD COLUMN duration_minutes INTEGER;
ALTER TABLE erp_demos ADD COLUMN matching_medium VARCHAR(100);
ALTER TABLE erp_demos ADD COLUMN matching_grade VARCHAR(100);
ALTER TABLE erp_demos ADD COLUMN matching_syllabus VARCHAR(100);
ALTER TABLE erp_demos ADD COLUMN feedback_outcome VARCHAR(30);
ALTER TABLE erp_demos ADD COLUMN feedback_note TEXT;
ALTER TABLE erp_demos ADD COLUMN enrollment_id INTEGER;
ALTER TABLE erp_demos ADD COLUMN agreement_id INTEGER;
CREATE INDEX erp_demos_starts_at_index ON erp_demos(starts_at);
CREATE TABLE erp_allocation_requests (
 id INTEGER PRIMARY KEY AUTOINCREMENT, unit_id INTEGER NOT NULL,
 reference VARCHAR(120) NOT NULL UNIQUE, fingerprint VARCHAR(64) NOT NULL, kind VARCHAR(20) NOT NULL,
 teacher_id INTEGER NOT NULL, demo_id INTEGER, enrollment_id INTEGER, agreement_id INTEGER,
 created_by INTEGER NOT NULL, created_at DATETIME, updated_at DATETIME
);
CREATE INDEX erp_allocation_requests_unit_id_index ON erp_allocation_requests(unit_id);
CREATE TRIGGER erp_d1_rev_erp_teacher_matching_profiles_insert AFTER INSERT ON erp_teacher_matching_profiles BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER erp_d1_rev_erp_teacher_matching_profiles_update AFTER UPDATE ON erp_teacher_matching_profiles BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER erp_d1_rev_erp_teacher_matching_profiles_delete AFTER DELETE ON erp_teacher_matching_profiles BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER erp_d1_rev_erp_allocation_requests_insert AFTER INSERT ON erp_allocation_requests BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER erp_d1_rev_erp_allocation_requests_update AFTER UPDATE ON erp_allocation_requests BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER erp_d1_rev_erp_allocation_requests_delete AFTER DELETE ON erp_allocation_requests BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
INSERT INTO migrations(migration,batch) VALUES('2026_10_26_160000_add_teacher_allocation',(SELECT COALESCE(MAX(batch),0)+1 FROM migrations));

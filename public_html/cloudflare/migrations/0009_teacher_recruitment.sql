CREATE TABLE erp_teacher_candidates (
 id INTEGER PRIMARY KEY AUTOINCREMENT, business_id INTEGER NOT NULL,
 identity_key VARCHAR(64) NOT NULL, name VARCHAR(255) NOT NULL,
 phone VARCHAR(40), email VARCHAR(255), teacher_id INTEGER, teacher_code VARCHAR(100),
 stage VARCHAR(24) NOT NULL DEFAULT 'APPLIED', qualification TEXT, subjects TEXT,
 grades TEXT, syllabuses TEXT, medium TEXT, availability TEXT, source_status TEXT,
 notes TEXT, cv_url VARCHAR(2000), registered_at DATETIME, created_at DATETIME, updated_at DATETIME,
 UNIQUE(business_id,identity_key)
);
CREATE INDEX erp_teacher_candidates_business_id_index ON erp_teacher_candidates(business_id);
CREATE INDEX erp_teacher_candidates_phone_index ON erp_teacher_candidates(phone);
CREATE INDEX erp_teacher_candidates_teacher_id_index ON erp_teacher_candidates(teacher_id);
CREATE INDEX erp_teacher_candidates_teacher_code_index ON erp_teacher_candidates(teacher_code);
CREATE INDEX erp_teacher_candidates_stage_index ON erp_teacher_candidates(stage);
CREATE TABLE erp_teacher_candidate_sources (
 id INTEGER PRIMARY KEY AUTOINCREMENT, candidate_id INTEGER NOT NULL,
 source_key VARCHAR(255) NOT NULL UNIQUE, kind VARCHAR(24) NOT NULL, payload TEXT NOT NULL,
 created_at DATETIME, updated_at DATETIME
);
CREATE INDEX erp_teacher_candidate_sources_candidate_id_index ON erp_teacher_candidate_sources(candidate_id);
CREATE TABLE erp_teacher_candidate_activities (
 id INTEGER PRIMARY KEY AUTOINCREMENT, candidate_id INTEGER NOT NULL, actor_id INTEGER NOT NULL,
 kind VARCHAR(40) NOT NULL, note TEXT NOT NULL, next_follow_up_on DATE, created_at DATETIME, updated_at DATETIME
);
CREATE INDEX erp_teacher_candidate_activities_candidate_id_index ON erp_teacher_candidate_activities(candidate_id);
CREATE TRIGGER erp_d1_rev_erp_teacher_candidates_insert AFTER INSERT ON erp_teacher_candidates BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER erp_d1_rev_erp_teacher_candidates_update AFTER UPDATE ON erp_teacher_candidates BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER erp_d1_rev_erp_teacher_candidates_delete AFTER DELETE ON erp_teacher_candidates BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER erp_d1_rev_erp_teacher_candidate_sources_insert AFTER INSERT ON erp_teacher_candidate_sources BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER erp_d1_rev_erp_teacher_candidate_sources_update AFTER UPDATE ON erp_teacher_candidate_sources BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER erp_d1_rev_erp_teacher_candidate_sources_delete AFTER DELETE ON erp_teacher_candidate_sources BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER erp_d1_rev_erp_teacher_candidate_activities_insert AFTER INSERT ON erp_teacher_candidate_activities BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER erp_d1_rev_erp_teacher_candidate_activities_update AFTER UPDATE ON erp_teacher_candidate_activities BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER erp_d1_rev_erp_teacher_candidate_activities_delete AFTER DELETE ON erp_teacher_candidate_activities BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
INSERT INTO migrations(migration,batch) VALUES('2026_10_26_140000_create_teacher_recruitment',(SELECT COALESCE(MAX(batch),0)+1 FROM migrations));

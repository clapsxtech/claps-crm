CREATE TABLE erp_sheet_sources (
 source_key VARCHAR(40) PRIMARY KEY NOT NULL,
 active_run_id VARCHAR(36), latest_run_id VARCHAR(36), lease_token VARCHAR(36), lease_until DATETIME, lease_tick INTEGER NOT NULL DEFAULT 0,
 last_attempt_at DATETIME, last_success_at DATETIME, last_status VARCHAR(40) NOT NULL DEFAULT 'never'
);
CREATE TABLE erp_sheet_runs (
 id VARCHAR(36) PRIMARY KEY NOT NULL, source_key VARCHAR(40) NOT NULL REFERENCES erp_sheet_sources(source_key),
 status VARCHAR(40) NOT NULL, reason VARCHAR(80), source_version VARCHAR(100), digest VARCHAR(64), contract_digest VARCHAR(64) NOT NULL,
 row_count INTEGER NOT NULL DEFAULT 0, issue_count INTEGER NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, completed_at DATETIME
);
CREATE INDEX erp_sheet_runs_source_key_created_at_index ON erp_sheet_runs(source_key,created_at);
CREATE TABLE erp_sheet_records (
 id INTEGER PRIMARY KEY AUTOINCREMENT, run_id VARCHAR(36) NOT NULL REFERENCES erp_sheet_runs(id),
 unit_id INTEGER REFERENCES erp_units(id), source_row INTEGER NOT NULL, source_identity VARCHAR(100), issue VARCHAR(80), payload TEXT NOT NULL,
 UNIQUE(run_id,source_row)
);
CREATE INDEX erp_sheet_records_run_id_unit_id_index ON erp_sheet_records(run_id,unit_id);
CREATE TRIGGER erp_d1_rev_erp_sheet_sources_insert AFTER INSERT ON erp_sheet_sources BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER erp_d1_rev_erp_sheet_sources_update AFTER UPDATE ON erp_sheet_sources BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER erp_d1_rev_erp_sheet_sources_delete AFTER DELETE ON erp_sheet_sources BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER erp_d1_rev_erp_sheet_runs_insert AFTER INSERT ON erp_sheet_runs BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER erp_d1_rev_erp_sheet_runs_update AFTER UPDATE ON erp_sheet_runs BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER erp_d1_rev_erp_sheet_runs_delete AFTER DELETE ON erp_sheet_runs BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER erp_d1_rev_erp_sheet_records_insert AFTER INSERT ON erp_sheet_records BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER erp_d1_rev_erp_sheet_records_update AFTER UPDATE ON erp_sheet_records BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER erp_d1_rev_erp_sheet_records_delete AFTER DELETE ON erp_sheet_records BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
INSERT INTO migrations(migration,batch) VALUES('2026_10_26_180000_create_sheet_transition',(SELECT COALESCE(MAX(batch),0)+1 FROM migrations));

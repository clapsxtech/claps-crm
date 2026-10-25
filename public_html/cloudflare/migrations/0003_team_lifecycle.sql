ALTER TABLE erp_units ADD COLUMN archived_at DATETIME NULL;
INSERT INTO migrations(migration,batch) SELECT '2026_10_25_150000_add_team_lifecycle', COALESCE(MAX(batch),0)+1 FROM migrations;

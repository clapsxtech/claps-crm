ALTER TABLE users ADD COLUMN two_factor_secret TEXT NULL;
ALTER TABLE users ADD COLUMN two_factor_recovery_codes TEXT NULL;
ALTER TABLE users ADD COLUMN two_factor_last_step INTEGER NULL;
INSERT INTO migrations(migration,batch) SELECT '2026_10_25_160000_add_account_two_factor', COALESCE(MAX(batch),0)+1 FROM migrations;

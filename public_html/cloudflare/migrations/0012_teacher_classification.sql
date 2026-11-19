ALTER TABLE erp_teachers ADD COLUMN levels VARCHAR(20);
ALTER TABLE erp_teachers ADD COLUMN category VARCHAR(20);
CREATE INDEX erp_teachers_category_index ON erp_teachers(category);

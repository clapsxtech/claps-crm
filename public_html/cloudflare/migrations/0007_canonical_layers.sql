-- Canonical profile, invoice and task layers for the legacy Sheets import.
CREATE TABLE "erp_student_profiles" (
  "id" integer primary key autoincrement not null,
  "unit_id" integer not null,
  "student_id" integer not null unique,
  "whatsapp_number" varchar,
  "student_contact" varchar,
  "country" varchar,
  "source" varchar,
  "section" varchar,
  "hour_rate_paise" integer,
  "email" varchar,
  "date_of_birth" date,
  "father_name" varchar,
  "mother_name" varchar,
  "address" text,
  "city" varchar,
  "pin_code" varchar,
  "school" varchar,
  "preferred_medium" varchar,
  "preferred_time" varchar,
  "network_connectivity" varchar,
  "gender" varchar,
  "parent_occupation" varchar,
  "student_occupation" varchar,
  "gmeet_link" varchar,
  "requirements" text,
  "concerns" text,
  "about_student" text,
  "about_parent" text,
  "feedback" text,
  "admin_label" varchar,
  "demo_status" varchar,
  "next_follow_up_on" date,
  "public_exam" varchar,
  "joined_class" varchar,
  "source_external_id" varchar,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "erp_student_profiles_unit_id_index" ON "erp_student_profiles" ("unit_id");

CREATE TABLE "erp_teacher_profiles" (
  "id" integer primary key autoincrement not null,
  "unit_id" integer not null,
  "teacher_id" integer not null unique,
  "whatsapp_number" varchar,
  "date_of_birth" date,
  "category" varchar,
  "teacher_type" varchar,
  "medium" varchar,
  "notes" text,
  "source_external_id" varchar,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "erp_teacher_profiles_unit_id_index" ON "erp_teacher_profiles" ("unit_id");

CREATE TABLE "erp_invoices" (
  "id" integer primary key autoincrement not null,
  "unit_id" integer not null,
  "student_id" integer not null,
  "invoice_number" varchar not null,
  "status" varchar not null default 'issued',
  "issue_date" date not null,
  "due_date" date,
  "currency" varchar not null default 'INR',
  "subtotal_paise" integer not null default 0,
  "discount_paise" integer not null default 0,
  "tax_paise" integer not null default 0,
  "total_paise" integer not null default 0,
  "billing_name" varchar,
  "billing_contact" varchar,
  "notes" text,
  "created_by" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  UNIQUE ("unit_id", "invoice_number")
);
CREATE INDEX "erp_invoices_unit_id_index" ON "erp_invoices" ("unit_id");
CREATE INDEX "erp_invoices_student_id_index" ON "erp_invoices" ("student_id");

CREATE TABLE "erp_invoice_lines" (
  "id" integer primary key autoincrement not null,
  "unit_id" integer not null,
  "invoice_id" integer not null,
  "description" varchar not null,
  "quantity" numeric not null default 1,
  "unit_rate_paise" integer not null default 0,
  "amount_paise" integer not null default 0,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "erp_invoice_lines_unit_id_index" ON "erp_invoice_lines" ("unit_id");
CREATE INDEX "erp_invoice_lines_invoice_id_index" ON "erp_invoice_lines" ("invoice_id");

CREATE TABLE "erp_task_templates" (
  "id" integer primary key autoincrement not null,
  "unit_id" integer not null,
  "department" varchar not null default 'operations',
  "name" varchar not null,
  "description" text,
  "default_points" numeric not null default 0,
  "checklist_definition" text,
  "active" tinyint(1) not null default 1,
  "created_by" integer,
  "created_at" datetime,
  "updated_at" datetime,
  UNIQUE ("unit_id", "department", "name")
);
CREATE INDEX "erp_task_templates_unit_id_index" ON "erp_task_templates" ("unit_id");

ALTER TABLE "erp_enrollments" ADD COLUMN "section" varchar;
ALTER TABLE "erp_enrollments" ADD COLUMN "grade" varchar;
ALTER TABLE "erp_enrollments" ADD COLUMN "syllabus" varchar;
ALTER TABLE "erp_enrollments" ADD COLUMN "source_external_id" varchar;
CREATE INDEX "erp_enrollments_section_index" ON "erp_enrollments" ("section");

ALTER TABLE "erp_ledger" ADD COLUMN "invoice_id" integer;
CREATE INDEX "erp_ledger_invoice_id_index" ON "erp_ledger" ("invoice_id");

ALTER TABLE "erp_tasks" ADD COLUMN "department" varchar;
ALTER TABLE "erp_tasks" ADD COLUMN "task_type" varchar;
ALTER TABLE "erp_tasks" ADD COLUMN "points" numeric not null default 0;
ALTER TABLE "erp_tasks" ADD COLUMN "completed_at" datetime;
ALTER TABLE "erp_tasks" ADD COLUMN "completed_by" integer;
ALTER TABLE "erp_tasks" ADD COLUMN "source" varchar;
ALTER TABLE "erp_tasks" ADD COLUMN "source_external_id" varchar;
CREATE INDEX "erp_tasks_department_index" ON "erp_tasks" ("department");
CREATE INDEX "erp_tasks_task_type_index" ON "erp_tasks" ("task_type");
CREATE INDEX "erp_tasks_source_index" ON "erp_tasks" ("unit_id", "source", "source_external_id");

ALTER TABLE "erp_task_items" ADD COLUMN "item_type" varchar not null default 'checklist';
ALTER TABLE "erp_task_items" ADD COLUMN "points" numeric not null default 0;
ALTER TABLE "erp_task_items" ADD COLUMN "required" tinyint(1) not null default 0;
ALTER TABLE "erp_task_items" ADD COLUMN "source_external_id" varchar;

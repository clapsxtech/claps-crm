-- Integrated admissions module. Lead history is separate from canonical ERP
-- students and ledger entries, so conversion links records instead of copying
-- the same student or payment into a second CRM database.
CREATE TABLE "erp_leads" (
  "id" integer primary key autoincrement not null,
  "unit_id" integer not null,
  "external_id" varchar,
  "name" varchar not null,
  "phone" varchar not null,
  "email" varchar,
  "enquiry_date" date not null,
  "status" varchar not null default 'LEAD',
  "grade" varchar,
  "subject" varchar,
  "syllabus" varchar,
  "source" varchar,
  "country" varchar,
  "notes" text,
  "student_phone" varchar,
  "monthly_fee_paise" integer,
  "fee_per_hour_paise" integer,
  "teacher_code" varchar,
  "teacher_section" varchar,
  "learning_requirement" text,
  "concern" text,
  "about_student" text,
  "about_parent" text,
  "assigned_to" integer,
  "created_by" integer not null,
  "is_hot" tinyint(1) not null default 0,
  "interest_status" varchar,
  "next_follow_up_on" date,
  "is_post_demo" tinyint(1) not null default 0,
  "student_id" integer,
  "joined_on" date,
  "admission_code" varchar,
  "lost_reason" text,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "erp_leads_unit_external_unique" ON "erp_leads" ("unit_id", "external_id");
CREATE UNIQUE INDEX "erp_leads_admission_code_unique" ON "erp_leads" ("admission_code");
CREATE INDEX "erp_leads_unit_id_index" ON "erp_leads" ("unit_id");
CREATE INDEX "erp_leads_pipeline_index" ON "erp_leads" ("unit_id", "status", "enquiry_date");
CREATE INDEX "erp_leads_phone_index" ON "erp_leads" ("unit_id", "phone");
CREATE INDEX "erp_leads_assigned_to_index" ON "erp_leads" ("assigned_to");
CREATE INDEX "erp_leads_next_follow_up_on_index" ON "erp_leads" ("next_follow_up_on");
CREATE INDEX "erp_leads_student_id_index" ON "erp_leads" ("student_id");

CREATE TABLE "erp_lead_activities" (
  "id" integer primary key autoincrement not null,
  "unit_id" integer not null,
  "lead_id" integer not null,
  "actor_id" integer,
  "kind" varchar not null,
  "channel" varchar,
  "outcome" varchar,
  "summary" text not null,
  "metadata" text,
  "occurred_at" datetime not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "erp_lead_activities_unit_id_index" ON "erp_lead_activities" ("unit_id");
CREATE INDEX "erp_lead_activity_timeline_index" ON "erp_lead_activities" ("unit_id", "lead_id", "occurred_at");

CREATE TABLE "erp_demos" (
  "id" integer primary key autoincrement not null,
  "unit_id" integer not null,
  "lead_id" integer not null,
  "student_id" integer,
  "teacher_id" integer,
  "subject_id" integer,
  "scheduled_on" date not null,
  "scheduled_time" varchar not null,
  "status" varchar not null default 'SCHEDULED',
  "meet_link" varchar,
  "created_by" integer not null,
  "scheduling_notes" text,
  "completed_at" datetime,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "erp_demos_unit_id_index" ON "erp_demos" ("unit_id");
CREATE INDEX "erp_demos_schedule_index" ON "erp_demos" ("unit_id", "scheduled_on", "status");
CREATE INDEX "erp_demos_lead_id_index" ON "erp_demos" ("lead_id");
CREATE INDEX "erp_demos_student_id_index" ON "erp_demos" ("student_id");
CREATE INDEX "erp_demos_teacher_id_index" ON "erp_demos" ("teacher_id");
CREATE INDEX "erp_demos_subject_id_index" ON "erp_demos" ("subject_id");

CREATE TABLE "erp_lead_reminders" (
  "id" integer primary key autoincrement not null,
  "unit_id" integer not null,
  "lead_id" integer not null,
  "due_on" date not null,
  "due_time" varchar,
  "text" varchar not null,
  "is_completed" tinyint(1) not null default 0,
  "completed_at" datetime,
  "completed_by" integer,
  "created_by" integer not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "erp_lead_reminders_unit_id_index" ON "erp_lead_reminders" ("unit_id");
CREATE INDEX "erp_lead_reminders_due_index" ON "erp_lead_reminders" ("unit_id", "due_on", "is_completed");
CREATE INDEX "erp_lead_reminders_lead_id_index" ON "erp_lead_reminders" ("lead_id");

CREATE TABLE "erp_lead_transfers" (
  "id" integer primary key autoincrement not null,
  "unit_id" integer not null,
  "lead_id" integer not null,
  "from_user_id" integer not null,
  "to_user_id" integer not null,
  "status" varchar not null default 'pending',
  "resolved_at" datetime,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "erp_lead_transfers_unit_id_index" ON "erp_lead_transfers" ("unit_id");
CREATE INDEX "erp_lead_transfers_inbox_index" ON "erp_lead_transfers" ("unit_id", "to_user_id", "status");
CREATE INDEX "erp_lead_transfers_lead_id_index" ON "erp_lead_transfers" ("lead_id");

CREATE TABLE "erp_teacher_enquiries" (
  "id" integer primary key autoincrement not null,
  "unit_id" integer not null,
  "phone" varchar not null,
  "enquiry_date" date not null,
  "source" varchar,
  "staff_id" integer not null,
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "erp_teacher_enquiries_unit_id_index" ON "erp_teacher_enquiries" ("unit_id");
CREATE INDEX "erp_teacher_enquiries_phone_index" ON "erp_teacher_enquiries" ("unit_id", "phone");

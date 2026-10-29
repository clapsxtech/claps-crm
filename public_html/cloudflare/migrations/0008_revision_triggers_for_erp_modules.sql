-- Keep the D1 optimistic revision guard authoritative for the additive ERP tables.
-- This is separate from the table migrations so it also repairs environments
-- where 0006/0007 were already applied before their trigger definitions existed.

CREATE TRIGGER IF NOT EXISTS erp_d1_rev_erp_leads_insert AFTER INSERT ON erp_leads BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER IF NOT EXISTS erp_d1_rev_erp_leads_update AFTER UPDATE ON erp_leads BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER IF NOT EXISTS erp_d1_rev_erp_leads_delete AFTER DELETE ON erp_leads BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;

CREATE TRIGGER IF NOT EXISTS erp_d1_rev_erp_lead_activities_insert AFTER INSERT ON erp_lead_activities BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER IF NOT EXISTS erp_d1_rev_erp_lead_activities_update AFTER UPDATE ON erp_lead_activities BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER IF NOT EXISTS erp_d1_rev_erp_lead_activities_delete AFTER DELETE ON erp_lead_activities BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;

CREATE TRIGGER IF NOT EXISTS erp_d1_rev_erp_demos_insert AFTER INSERT ON erp_demos BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER IF NOT EXISTS erp_d1_rev_erp_demos_update AFTER UPDATE ON erp_demos BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER IF NOT EXISTS erp_d1_rev_erp_demos_delete AFTER DELETE ON erp_demos BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;

CREATE TRIGGER IF NOT EXISTS erp_d1_rev_erp_lead_reminders_insert AFTER INSERT ON erp_lead_reminders BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER IF NOT EXISTS erp_d1_rev_erp_lead_reminders_update AFTER UPDATE ON erp_lead_reminders BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER IF NOT EXISTS erp_d1_rev_erp_lead_reminders_delete AFTER DELETE ON erp_lead_reminders BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;

CREATE TRIGGER IF NOT EXISTS erp_d1_rev_erp_lead_transfers_insert AFTER INSERT ON erp_lead_transfers BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER IF NOT EXISTS erp_d1_rev_erp_lead_transfers_update AFTER UPDATE ON erp_lead_transfers BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER IF NOT EXISTS erp_d1_rev_erp_lead_transfers_delete AFTER DELETE ON erp_lead_transfers BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;

CREATE TRIGGER IF NOT EXISTS erp_d1_rev_erp_teacher_enquiries_insert AFTER INSERT ON erp_teacher_enquiries BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER IF NOT EXISTS erp_d1_rev_erp_teacher_enquiries_update AFTER UPDATE ON erp_teacher_enquiries BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER IF NOT EXISTS erp_d1_rev_erp_teacher_enquiries_delete AFTER DELETE ON erp_teacher_enquiries BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;

CREATE TRIGGER IF NOT EXISTS erp_d1_rev_erp_student_profiles_insert AFTER INSERT ON erp_student_profiles BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER IF NOT EXISTS erp_d1_rev_erp_student_profiles_update AFTER UPDATE ON erp_student_profiles BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER IF NOT EXISTS erp_d1_rev_erp_student_profiles_delete AFTER DELETE ON erp_student_profiles BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;

CREATE TRIGGER IF NOT EXISTS erp_d1_rev_erp_teacher_profiles_insert AFTER INSERT ON erp_teacher_profiles BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER IF NOT EXISTS erp_d1_rev_erp_teacher_profiles_update AFTER UPDATE ON erp_teacher_profiles BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER IF NOT EXISTS erp_d1_rev_erp_teacher_profiles_delete AFTER DELETE ON erp_teacher_profiles BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;

CREATE TRIGGER IF NOT EXISTS erp_d1_rev_erp_invoices_insert AFTER INSERT ON erp_invoices BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER IF NOT EXISTS erp_d1_rev_erp_invoices_update AFTER UPDATE ON erp_invoices BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER IF NOT EXISTS erp_d1_rev_erp_invoices_delete AFTER DELETE ON erp_invoices BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;

CREATE TRIGGER IF NOT EXISTS erp_d1_rev_erp_invoice_lines_insert AFTER INSERT ON erp_invoice_lines BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER IF NOT EXISTS erp_d1_rev_erp_invoice_lines_update AFTER UPDATE ON erp_invoice_lines BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER IF NOT EXISTS erp_d1_rev_erp_invoice_lines_delete AFTER DELETE ON erp_invoice_lines BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;

CREATE TRIGGER IF NOT EXISTS erp_d1_rev_erp_task_templates_insert AFTER INSERT ON erp_task_templates BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER IF NOT EXISTS erp_d1_rev_erp_task_templates_update AFTER UPDATE ON erp_task_templates BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;
CREATE TRIGGER IF NOT EXISTS erp_d1_rev_erp_task_templates_delete AFTER DELETE ON erp_task_templates BEGIN UPDATE erp_d1_revision SET revision=revision+1 WHERE id=1; END;

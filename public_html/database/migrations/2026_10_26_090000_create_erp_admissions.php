<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admissions is the integrated replacement for the standalone claps-crm store.
 * Leads retain the sales history; joined students and money remain canonical in
 * erp_students and erp_ledger.
 */
class CreateErpAdmissions extends Migration {
    public function up(): void
    {
        Schema::create('erp_leads', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('unit_id')->index();
            $t->string('external_id', 160)->nullable();
            $t->string('name', 255);
            $t->string('phone', 40);
            $t->string('email', 255)->nullable();
            $t->date('enquiry_date');
            $t->string('status', 20)->default('LEAD');
            $t->string('grade', 100)->nullable();
            $t->string('subject', 100)->nullable();
            $t->string('syllabus', 100)->nullable();
            $t->string('source', 100)->nullable();
            $t->string('country', 8)->nullable();
            $t->text('notes')->nullable();
            $t->string('student_phone', 40)->nullable();
            $t->unsignedBigInteger('monthly_fee_paise')->nullable();
            $t->unsignedBigInteger('fee_per_hour_paise')->nullable();
            $t->string('teacher_code', 100)->nullable();
            $t->string('teacher_section', 100)->nullable();
            $t->text('learning_requirement')->nullable();
            $t->text('concern')->nullable();
            $t->text('about_student')->nullable();
            $t->text('about_parent')->nullable();
            $t->unsignedBigInteger('assigned_to')->nullable()->index();
            $t->unsignedBigInteger('created_by')->index();
            $t->boolean('is_hot')->default(false);
            $t->string('interest_status', 30)->nullable();
            $t->date('next_follow_up_on')->nullable()->index();
            $t->boolean('is_post_demo')->default(false);
            $t->unsignedBigInteger('student_id')->nullable()->index();
            $t->date('joined_on')->nullable();
            $t->string('admission_code', 32)->nullable()->unique();
            $t->text('lost_reason')->nullable();
            $t->timestamps();
            $t->unique(['unit_id', 'external_id'], 'erp_leads_unit_external_unique');
            $t->index(['unit_id', 'status', 'enquiry_date'], 'erp_leads_pipeline_index');
            $t->index(['unit_id', 'phone'], 'erp_leads_phone_index');
        });

        Schema::create('erp_lead_activities', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('unit_id')->index();
            $t->unsignedBigInteger('lead_id')->index();
            $t->unsignedBigInteger('actor_id')->nullable();
            $t->string('kind', 40);
            $t->string('channel', 20)->nullable();
            $t->string('outcome', 255)->nullable();
            $t->text('summary');
            $t->text('metadata')->nullable();
            $t->dateTime('occurred_at');
            $t->timestamps();
            $t->index(['unit_id', 'lead_id', 'occurred_at'], 'erp_lead_activity_timeline_index');
        });

        Schema::create('erp_demos', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('unit_id')->index();
            $t->unsignedBigInteger('lead_id')->index();
            $t->unsignedBigInteger('student_id')->nullable()->index();
            $t->unsignedBigInteger('teacher_id')->nullable()->index();
            $t->unsignedBigInteger('subject_id')->nullable()->index();
            $t->date('scheduled_on');
            $t->string('scheduled_time', 5);
            $t->string('status', 20)->default('SCHEDULED');
            $t->string('meet_link', 500)->nullable();
            $t->unsignedBigInteger('created_by');
            $t->text('scheduling_notes')->nullable();
            $t->dateTime('completed_at')->nullable();
            $t->timestamps();
            $t->index(['unit_id', 'scheduled_on', 'status'], 'erp_demos_schedule_index');
        });

        Schema::create('erp_lead_reminders', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('unit_id')->index();
            $t->unsignedBigInteger('lead_id')->index();
            $t->date('due_on');
            $t->string('due_time', 5)->nullable();
            $t->string('text', 500);
            $t->boolean('is_completed')->default(false);
            $t->dateTime('completed_at')->nullable();
            $t->unsignedBigInteger('completed_by')->nullable();
            $t->unsignedBigInteger('created_by');
            $t->timestamps();
            $t->index(['unit_id', 'due_on', 'is_completed'], 'erp_lead_reminders_due_index');
        });

        Schema::create('erp_lead_transfers', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('unit_id')->index();
            $t->unsignedBigInteger('lead_id')->index();
            $t->unsignedBigInteger('from_user_id');
            $t->unsignedBigInteger('to_user_id');
            $t->string('status', 20)->default('pending');
            $t->dateTime('resolved_at')->nullable();
            $t->timestamps();
            $t->index(['unit_id', 'to_user_id', 'status'], 'erp_lead_transfers_inbox_index');
        });

        Schema::create('erp_teacher_enquiries', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('unit_id')->index();
            $t->string('phone', 40);
            $t->date('enquiry_date');
            $t->string('source', 100)->nullable();
            $t->unsignedBigInteger('staff_id');
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['unit_id', 'phone'], 'erp_teacher_enquiries_phone_index');
        });
    }

    public function down(): void
    {
        foreach (['erp_teacher_enquiries', 'erp_lead_transfers', 'erp_lead_reminders', 'erp_demos', 'erp_lead_activities', 'erp_leads'] as $table) {
            Schema::dropIfExists($table);
        }
    }
}

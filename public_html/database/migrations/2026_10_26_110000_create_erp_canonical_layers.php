<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Canonical extensions for the legacy Sheets data.
 *
 * The old workbooks repeat the same student, teacher, invoice and task fields
 * across many derived views. These tables retain the source detail once while
 * keeping the existing ERP students, teachers, ledger and tasks as the domain
 * records used by the application.
 */
class CreateErpCanonicalLayers extends Migration
{
    public function up(): void
    {
        Schema::create('erp_student_profiles', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('unit_id')->index();
            $t->unsignedBigInteger('student_id')->unique();
            $t->string('whatsapp_number', 40)->nullable();
            $t->string('student_contact', 40)->nullable();
            $t->string('country', 8)->nullable();
            $t->string('source', 100)->nullable();
            $t->string('section', 80)->nullable();
            $t->bigInteger('hour_rate_paise')->nullable();
            $t->string('email', 255)->nullable();
            $t->date('date_of_birth')->nullable();
            $t->string('father_name', 255)->nullable();
            $t->string('mother_name', 255)->nullable();
            $t->text('address')->nullable();
            $t->string('city', 120)->nullable();
            $t->string('pin_code', 20)->nullable();
            $t->string('school', 255)->nullable();
            $t->string('preferred_medium', 100)->nullable();
            $t->string('preferred_time', 100)->nullable();
            $t->string('network_connectivity', 100)->nullable();
            $t->string('gender', 40)->nullable();
            $t->string('parent_occupation', 160)->nullable();
            $t->string('student_occupation', 160)->nullable();
            $t->string('gmeet_link', 500)->nullable();
            $t->text('requirements')->nullable();
            $t->text('concerns')->nullable();
            $t->text('about_student')->nullable();
            $t->text('about_parent')->nullable();
            $t->text('feedback')->nullable();
            $t->string('admin_label', 120)->nullable();
            $t->string('demo_status', 80)->nullable();
            $t->date('next_follow_up_on')->nullable();
            $t->string('public_exam', 120)->nullable();
            $t->string('joined_class', 160)->nullable();
            $t->string('source_external_id', 160)->nullable();
            $t->timestamps();
        });

        Schema::create('erp_teacher_profiles', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('unit_id')->index();
            $t->unsignedBigInteger('teacher_id')->unique();
            $t->string('whatsapp_number', 40)->nullable();
            $t->date('date_of_birth')->nullable();
            $t->string('category', 100)->nullable();
            $t->string('teacher_type', 100)->nullable();
            $t->string('medium', 100)->nullable();
            $t->text('notes')->nullable();
            $t->string('source_external_id', 160)->nullable();
            $t->timestamps();
        });

        Schema::create('erp_invoices', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('unit_id')->index();
            $t->unsignedBigInteger('student_id')->index();
            $t->string('invoice_number', 120);
            $t->string('status', 24)->default('issued');
            $t->date('issue_date');
            $t->date('due_date')->nullable();
            $t->string('currency', 3)->default('INR');
            $t->bigInteger('subtotal_paise')->default(0);
            $t->bigInteger('discount_paise')->default(0);
            $t->bigInteger('tax_paise')->default(0);
            $t->bigInteger('total_paise')->default(0);
            $t->string('billing_name', 255)->nullable();
            $t->string('billing_contact', 255)->nullable();
            $t->text('notes')->nullable();
            $t->unsignedBigInteger('created_by');
            $t->timestamps();
            $t->unique(['unit_id', 'invoice_number'], 'erp_invoice_unit_number_unique');
        });

        Schema::create('erp_invoice_lines', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('unit_id')->index();
            $t->unsignedBigInteger('invoice_id')->index();
            $t->string('description', 255);
            $t->decimal('quantity', 10, 2)->default(1);
            $t->bigInteger('unit_rate_paise')->default(0);
            $t->bigInteger('amount_paise')->default(0);
            $t->timestamps();
        });

        Schema::create('erp_task_templates', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('unit_id')->index();
            $t->string('department', 80)->default('operations');
            $t->string('name', 160);
            $t->text('description')->nullable();
            $t->decimal('default_points', 8, 2)->default(0);
            $t->text('checklist_definition')->nullable();
            $t->boolean('active')->default(true);
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
            $t->unique(['unit_id', 'department', 'name'], 'erp_task_template_unique');
        });

        Schema::table('erp_enrollments', function (Blueprint $t) {
            $t->string('section', 80)->nullable()->index();
            $t->string('grade', 100)->nullable();
            $t->string('syllabus', 100)->nullable();
            $t->string('source_external_id', 160)->nullable();
        });

        Schema::table('erp_ledger', function (Blueprint $t) {
            $t->unsignedBigInteger('invoice_id')->nullable()->index();
        });

        Schema::table('erp_tasks', function (Blueprint $t) {
            $t->string('department', 80)->nullable()->index();
            $t->string('task_type', 120)->nullable()->index();
            $t->decimal('points', 8, 2)->default(0);
            $t->dateTime('completed_at')->nullable();
            $t->unsignedBigInteger('completed_by')->nullable();
            $t->string('source', 80)->nullable();
            $t->string('source_external_id', 160)->nullable();
            $t->index(['unit_id', 'source', 'source_external_id'], 'erp_tasks_source_index');
        });

        Schema::table('erp_task_items', function (Blueprint $t) {
            $t->string('item_type', 40)->default('checklist');
            $t->decimal('points', 8, 2)->default(0);
            $t->boolean('required')->default(false);
            $t->string('source_external_id', 160)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('erp_task_items', function (Blueprint $t) {
            $t->dropColumn(['item_type', 'points', 'required', 'source_external_id']);
        });
        Schema::table('erp_tasks', function (Blueprint $t) {
            $t->dropIndex('erp_tasks_source_index');
            $t->dropColumn(['department', 'task_type', 'points', 'completed_at', 'completed_by', 'source', 'source_external_id']);
        });
        Schema::table('erp_ledger', function (Blueprint $t) {
            $t->dropIndex(['invoice_id']);
            $t->dropColumn('invoice_id');
        });
        Schema::table('erp_enrollments', function (Blueprint $t) {
            $t->dropIndex(['section']);
            $t->dropColumn(['section', 'grade', 'syllabus', 'source_external_id']);
        });
        foreach (['erp_task_templates', 'erp_invoice_lines', 'erp_invoices', 'erp_teacher_profiles', 'erp_student_profiles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
}

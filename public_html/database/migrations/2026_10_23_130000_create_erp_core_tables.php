<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateErpCoreTables extends Migration
{
    public function up()
    {
        Schema::create('erp_units', function (Blueprint $t) {
            $t->bigIncrements('id'); $t->string('code')->unique(); $t->string('name'); $t->string('phone')->nullable(); $t->string('timezone')->default('Asia/Kolkata');
            $t->timestamps();
        });
        Schema::create('erp_memberships', function (Blueprint $t) {
            $t->bigIncrements('id'); $t->unsignedBigInteger('user_id'); $t->unsignedBigInteger('unit_id'); $t->string('role'); $t->unique(['user_id','unit_id']);
            $t->timestamps();
        });
        Schema::create('erp_students', function (Blueprint $t) {
            $t->bigIncrements('id'); $t->unsignedBigInteger('unit_id')->index(); $t->string('code')->unique(); $t->string('name'); $t->date('registered_on'); $t->string('timezone'); $t->string('status')->default('active'); $t->string('grade'); $t->string('syllabus'); $t->string('guardian_contact')->nullable(); $t->string('meeting_url')->nullable();
            $t->timestamps();
        });
        Schema::create('erp_teachers', function (Blueprint $t) {
            $t->bigIncrements('id'); $t->unsignedBigInteger('user_id')->nullable()->unique(); $t->string('code')->unique(); $t->string('name'); $t->string('timezone'); $t->string('status')->default('active');
            $t->timestamps();
        });
        Schema::create('erp_teacher_units', function (Blueprint $t) {
            $t->bigIncrements('id'); $t->unsignedBigInteger('teacher_id')->unique(); $t->unsignedBigInteger('unit_id'); $t->unique(['teacher_id','unit_id']);
            $t->timestamps();
        });
        Schema::create('erp_subjects', function (Blueprint $t) {
            $t->bigIncrements('id'); $t->string('name')->unique();
            $t->timestamps();
        });
        Schema::create('erp_eligibilities', function (Blueprint $t) {
            $t->bigIncrements('id'); $t->unsignedBigInteger('teacher_id'); $t->unsignedBigInteger('subject_id'); $t->string('grade',100); $t->string('syllabus',100); $t->unique(['teacher_id','subject_id','grade','syllabus'],'erp_eligibility_unique');
            $t->timestamps();
        });
        Schema::create('erp_enrollments', function (Blueprint $t) {
            $t->bigIncrements('id'); $t->unsignedBigInteger('unit_id')->index(); $t->unsignedBigInteger('student_id')->index(); $t->unsignedBigInteger('teacher_id')->index(); $t->unsignedBigInteger('subject_id'); $t->date('starts_on'); $t->date('ends_on')->nullable(); $t->unsignedInteger('student_rate_paise'); $t->unsignedInteger('teacher_rate_paise'); $t->string('currency')->default('INR'); $t->string('status')->default('active');
            $t->timestamps();
        });
        Schema::create('erp_availability', function (Blueprint $t) {
            $t->bigIncrements('id'); $t->unsignedBigInteger('unit_id')->index(); $t->unsignedBigInteger('student_id')->nullable(); $t->unsignedBigInteger('teacher_id')->nullable(); $t->unsignedTinyInteger('weekday'); $t->string('start_time',5); $t->string('end_time',5); $t->string('timezone'); $t->date('starts_on'); $t->date('ends_on')->nullable();
            $t->timestamps();
        });
        Schema::create('erp_exceptions', function (Blueprint $t) {
            $t->bigIncrements('id'); $t->unsignedBigInteger('unit_id')->index(); $t->unsignedBigInteger('student_id')->nullable(); $t->unsignedBigInteger('teacher_id')->nullable(); $t->date('date'); $t->text('reason'); $t->timestamps();
        });
        Schema::create('erp_agreements', function (Blueprint $t) {
            $t->bigIncrements('id'); $t->unsignedBigInteger('unit_id')->index(); $t->unsignedBigInteger('enrollment_id'); $t->unsignedBigInteger('replaces_agreement_id')->nullable()->unique(); $t->unsignedTinyInteger('weekday'); $t->string('start_time',5); $t->unsignedInteger('duration_minutes'); $t->string('timezone'); $t->date('starts_on'); $t->date('ends_on')->nullable(); $t->string('status')->default('proposed'); $t->dateTime('teacher_confirmed_at')->nullable(); $t->dateTime('student_confirmed_at')->nullable(); $t->string('confirmation_channel')->nullable(); $t->unsignedBigInteger('confirmed_by')->nullable(); $t->text('confirmation_note')->nullable();
            $t->timestamps();
        });
        Schema::create('erp_lessons', function (Blueprint $t) {
            $t->bigIncrements('id'); $t->unsignedBigInteger('unit_id')->index(); $t->unsignedBigInteger('enrollment_id'); $t->unsignedBigInteger('agreement_id')->nullable(); $t->unsignedBigInteger('student_id')->index(); $t->unsignedBigInteger('teacher_id')->index(); $t->unsignedBigInteger('subject_id'); $t->dateTime('starts_at')->index(); $t->dateTime('ends_at'); $t->string('status')->default('scheduled'); $t->string('kind')->default('regular'); $t->unsignedBigInteger('replaces_lesson_id')->nullable()->unique(); $t->unsignedInteger('student_rate_paise'); $t->unsignedInteger('teacher_rate_paise'); $t->string('currency')->default('INR'); $t->text('cancellation_reason')->nullable(); $t->unique(['agreement_id','starts_at']);
            $t->timestamps();
        });
        Schema::create('erp_attendance_batches', function (Blueprint $t) {
            $t->bigIncrements('id'); $t->unsignedBigInteger('unit_id')->index(); $t->string('reference')->unique(); $t->text('snapshot'); $t->unsignedBigInteger('imported_by'); $t->dateTime('imported_at'); $t->timestamps();
        });
        Schema::create('erp_attendance', function (Blueprint $t) {
            $t->bigIncrements('id'); $t->unsignedBigInteger('unit_id')->index(); $t->unsignedBigInteger('lesson_id')->unique(); $t->unsignedInteger('actual_minutes'); $t->string('status')->default('submitted'); $t->unsignedBigInteger('submitted_by'); $t->unsignedBigInteger('reviewed_by')->nullable(); $t->dateTime('reviewed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('erp_ledger', function (Blueprint $t) {
            $t->bigIncrements('id'); $t->unsignedBigInteger('unit_id')->index(); $t->unsignedBigInteger('student_id')->nullable()->index(); $t->unsignedBigInteger('teacher_id')->nullable()->index(); $t->unsignedBigInteger('attendance_id')->nullable(); $t->string('kind'); $t->bigInteger('amount_paise'); $t->string('currency'); $t->string('reference')->unique(); $t->string('event_key')->unique(); $t->unsignedBigInteger('reverses_id')->nullable()->unique(); $t->dateTime('occurred_at'); $t->unsignedBigInteger('created_by');
            $t->timestamps();
        });
        Schema::create('erp_duplicate_reviews', function (Blueprint $t) {
            $t->bigIncrements('id'); $t->unsignedBigInteger('unit_id')->index(); $t->unsignedBigInteger('attendance_id'); $t->unsignedBigInteger('other_attendance_id'); $t->string('status')->default('pending'); $t->string('channel')->default('gallabox'); $t->text('teacher_reply')->nullable(); $t->text('reason')->nullable(); $t->unsignedBigInteger('reviewed_by')->nullable(); $t->dateTime('reviewed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('erp_audit', function (Blueprint $t) {
            $t->bigIncrements('id'); $t->unsignedBigInteger('unit_id')->nullable()->index(); $t->unsignedBigInteger('actor_id')->nullable(); $t->string('action'); $t->string('entity_type'); $t->unsignedBigInteger('entity_id')->nullable(); $t->text('payload');
            $t->timestamps();
        });
        Schema::create('erp_tasks', function (Blueprint $t) {
            $t->bigIncrements('id'); $t->unsignedBigInteger('unit_id')->index(); $t->unsignedBigInteger('student_id')->nullable(); $t->unsignedBigInteger('assigned_to')->nullable(); $t->string('title'); $t->text('notes')->nullable(); $t->date('due_on')->nullable(); $t->string('status')->default('open'); $t->unsignedBigInteger('created_by');
            $t->timestamps();
        });
        Schema::create('erp_marks', function (Blueprint $t) {
            $t->bigIncrements('id'); $t->unsignedBigInteger('unit_id')->index(); $t->unsignedBigInteger('enrollment_id'); $t->string('assessment'); $t->date('assessed_on'); $t->unsignedInteger('score'); $t->unsignedInteger('maximum'); $t->text('notes')->nullable(); $t->unsignedBigInteger('created_by');
            $t->timestamps();
        });
    }
    public function down()
    {
        Schema::dropIfExists('erp_marks');
        Schema::dropIfExists('erp_tasks');
        Schema::dropIfExists('erp_audit');
        Schema::dropIfExists('erp_duplicate_reviews');
        Schema::dropIfExists('erp_ledger');
        Schema::dropIfExists('erp_attendance');
        Schema::dropIfExists('erp_attendance_batches');
        Schema::dropIfExists('erp_lessons');
        Schema::dropIfExists('erp_agreements');
        Schema::dropIfExists('erp_exceptions');
        Schema::dropIfExists('erp_availability');
        Schema::dropIfExists('erp_enrollments');
        Schema::dropIfExists('erp_eligibilities');
        Schema::dropIfExists('erp_subjects');
        Schema::dropIfExists('erp_teacher_units');
        Schema::dropIfExists('erp_teachers');
        Schema::dropIfExists('erp_students');
        Schema::dropIfExists('erp_memberships');
        Schema::dropIfExists('erp_units');
    }
}

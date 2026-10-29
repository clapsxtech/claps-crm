<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
class AddErpTuitionWorkflows extends Migration {
 public function up(): void {
  Schema::table('erp_attendance', function(Blueprint $t) {
   $t->dateTime('actual_starts_at')->nullable(); $t->text('change_reason')->nullable(); $t->boolean('submitted_late')->default(false);
   $t->dateTime('parent_shared_at')->nullable(); $t->dateTime('parent_deadline_at')->nullable();
   $t->string('parent_review_status')->default('unshared'); $t->text('parent_review_note')->nullable();
  });
  Schema::create('erp_internal_notes', function(Blueprint $t) {
   $t->id(); $t->unsignedBigInteger('unit_id'); $t->unsignedBigInteger('lesson_id'); $t->unsignedBigInteger('created_by');
   $t->string('kind'); $t->string('outcome')->nullable(); $t->text('note')->nullable(); $t->timestamps(); $t->index(['unit_id','lesson_id']);
  });
  Schema::create('erp_candidates', function(Blueprint $t) {
   $t->id(); $t->unsignedBigInteger('unit_id'); $t->string('name'); $t->string('contact'); $t->text('qualification');
   $t->text('subjects'); $t->text('levels'); $t->text('languages')->nullable(); $t->text('availability')->nullable();
   $t->string('status')->default('pool'); $t->unsignedBigInteger('created_by'); $t->timestamps(); $t->index(['unit_id','status']);
  });
  Schema::create('erp_candidate_interviews', function(Blueprint $t) {
   $t->id(); $t->unsignedBigInteger('unit_id'); $t->unsignedBigInteger('candidate_id'); $t->unsignedBigInteger('created_by');
   $t->unsignedSmallInteger('score'); $t->text('recording_url')->nullable(); $t->text('strengths')->nullable(); $t->text('concerns')->nullable();
   $t->string('decision'); $t->timestamps(); $t->index(['unit_id','candidate_id']);
  });
 }
 public function down(): void {
  Schema::dropIfExists('erp_candidate_interviews'); Schema::dropIfExists('erp_candidates'); Schema::dropIfExists('erp_internal_notes');
  Schema::table('erp_attendance',fn(Blueprint $t)=>$t->dropColumn(['actual_starts_at','change_reason','submitted_late','parent_shared_at','parent_deadline_at','parent_review_status','parent_review_note']));
 }
}

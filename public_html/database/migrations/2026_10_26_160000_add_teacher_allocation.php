<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('erp_teacher_candidates',function(Blueprint $t){
            $t->unsignedBigInteger('merged_into_id')->nullable()->index();
            $t->timestamp('reviewed_at')->nullable();
            $t->unsignedBigInteger('reviewed_by')->nullable();
        });
        Schema::create('erp_teacher_matching_profiles',function(Blueprint $t){
            $t->bigIncrements('id');$t->unsignedBigInteger('teacher_id')->unique();
            $t->text('mediums');$t->unsignedBigInteger('verified_by');
            $t->timestamp('verified_at');$t->text('verification_note');$t->timestamps();
        });
        Schema::table('erp_demos',function(Blueprint $t){
            $t->dateTime('starts_at')->nullable()->index();$t->dateTime('ends_at')->nullable();
            $t->string('timezone')->nullable();$t->unsignedInteger('duration_minutes')->nullable();
            $t->string('matching_medium',100)->nullable();$t->string('matching_grade',100)->nullable();$t->string('matching_syllabus',100)->nullable();
            $t->string('feedback_outcome',30)->nullable();$t->text('feedback_note')->nullable();
            $t->unsignedBigInteger('enrollment_id')->nullable();$t->unsignedBigInteger('agreement_id')->nullable();
        });
        Schema::create('erp_allocation_requests',function(Blueprint $t){
            $t->bigIncrements('id');$t->unsignedBigInteger('unit_id')->index();
            $t->string('reference',120)->unique();$t->string('fingerprint',64);$t->string('kind',20);
            $t->unsignedBigInteger('teacher_id');$t->unsignedBigInteger('demo_id')->nullable();
            $t->unsignedBigInteger('enrollment_id')->nullable();$t->unsignedBigInteger('agreement_id')->nullable();
            $t->unsignedBigInteger('created_by');$t->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('erp_allocation_requests');
        Schema::table('erp_demos',fn(Blueprint $t)=>$t->dropColumn(['starts_at','ends_at','timezone','duration_minutes','matching_medium','matching_grade','matching_syllabus','feedback_outcome','feedback_note','enrollment_id','agreement_id']));
        Schema::dropIfExists('erp_teacher_matching_profiles');
        Schema::table('erp_teacher_candidates',fn(Blueprint $t)=>$t->dropColumn(['merged_into_id','reviewed_at','reviewed_by']));
    }
};

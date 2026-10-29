<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('erp_teacher_candidates', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('business_id')->index();
            $t->string('identity_key', 64);
            $t->string('name');
            $t->string('phone', 40)->nullable()->index();
            $t->string('email')->nullable();
            $t->unsignedBigInteger('teacher_id')->nullable()->index();
            $t->string('teacher_code', 100)->nullable()->index();
            $t->string('stage', 24)->default('APPLIED')->index();
            foreach (['qualification','subjects','grades','syllabuses','medium','availability','source_status','notes'] as $field) $t->text($field)->nullable();
            $t->string('cv_url', 2000)->nullable();
            $t->timestamp('registered_at')->nullable();
            $t->timestamps();
            $t->unique(['business_id','identity_key']);
        });
        Schema::create('erp_teacher_candidate_sources', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('candidate_id')->index();
            $t->string('source_key', 255)->unique();
            $t->string('kind', 24);
            $t->text('payload');
            $t->timestamps();
        });
        Schema::create('erp_teacher_candidate_activities', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('candidate_id')->index();
            $t->unsignedBigInteger('actor_id');
            $t->string('kind', 40);
            $t->text('note');
            $t->date('next_follow_up_on')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['erp_teacher_candidate_activities','erp_teacher_candidate_sources','erp_teacher_candidates'] as $table) Schema::dropIfExists($table);
    }
};

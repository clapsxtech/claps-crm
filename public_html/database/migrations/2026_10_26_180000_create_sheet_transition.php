<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('erp_sheet_sources', function (Blueprint $t) {
            $t->string('source_key', 40)->primary();
            $t->uuid('active_run_id')->nullable();
            $t->uuid('latest_run_id')->nullable();
            $t->uuid('lease_token')->nullable();
            $t->timestamp('lease_until')->nullable();
            $t->unsignedBigInteger('lease_tick')->default(0);
            $t->timestamp('last_attempt_at')->nullable();
            $t->timestamp('last_success_at')->nullable();
            $t->string('last_status', 40)->default('never');
        });
        Schema::create('erp_sheet_runs', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('source_key', 40);
            $t->foreign('source_key')->references('source_key')->on('erp_sheet_sources');
            $t->string('status', 40); $t->string('reason', 80)->nullable();
            $t->string('source_version', 100)->nullable(); $t->string('digest', 64)->nullable();
            $t->string('contract_digest', 64);
            $t->unsignedInteger('row_count')->default(0); $t->unsignedInteger('issue_count')->default(0);
            $t->timestamp('created_at'); $t->timestamp('completed_at')->nullable();
            $t->index(['source_key','created_at']);
        });
        Schema::create('erp_sheet_records', function (Blueprint $t) {
            $t->bigIncrements('id'); $t->uuid('run_id');
            $t->foreign('run_id')->references('id')->on('erp_sheet_runs');
            $t->unsignedBigInteger('unit_id')->nullable();
            $t->foreign('unit_id')->references('id')->on('erp_units');
            $t->unsignedInteger('source_row'); $t->string('source_identity', 100)->nullable();
            $t->string('issue', 80)->nullable(); $t->text('payload');
            $t->unique(['run_id','source_row']);
            $t->index(['run_id','unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_sheet_records');
        Schema::dropIfExists('erp_sheet_runs');
        Schema::dropIfExists('erp_sheet_sources');
    }
};

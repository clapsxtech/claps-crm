<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
class CreateErpBusinessWorkflows extends Migration
{
    public function up()
    {
        Schema::create('erp_payment_requests',function(Blueprint $t){$t->bigIncrements('id');$t->unsignedBigInteger('unit_id')->index();$t->unsignedBigInteger('student_id');$t->string('reference',120)->unique();$t->unsignedBigInteger('amount_paise');$t->string('status')->default('pending');$t->date('requested_on');$t->date('due_on')->nullable();$t->text('notes')->nullable();$t->unsignedBigInteger('created_by');$t->timestamps();});
        Schema::create('erp_request_allocations',function(Blueprint $t){$t->bigIncrements('id');$t->unsignedBigInteger('unit_id')->index();$t->unsignedBigInteger('request_id');$t->unsignedBigInteger('payment_id');$t->unsignedBigInteger('amount_paise');$t->timestamps();$t->unique(['request_id','payment_id']);});
        Schema::create('erp_task_items',function(Blueprint $t){$t->bigIncrements('id');$t->unsignedBigInteger('unit_id')->index();$t->unsignedBigInteger('task_id');$t->string('title');$t->dateTime('completed_at')->nullable();$t->unsignedBigInteger('completed_by')->nullable();$t->timestamps();});
        Schema::create('erp_followups',function(Blueprint $t){$t->bigIncrements('id');$t->unsignedBigInteger('unit_id')->index();$t->unsignedBigInteger('student_id');$t->string('contact_channel');$t->text('note');$t->text('outcome');$t->date('next_due_on')->nullable();$t->unsignedBigInteger('created_by');$t->timestamps();});
        Schema::create('erp_migration_batches',function(Blueprint $t){$t->bigIncrements('id');$t->string('source_id',128);$t->date('cutoff');$t->char('digest',64)->unique();$t->text('manifest');$t->text('counts');$t->text('totals');$t->unsignedBigInteger('actor_id');$t->timestamp('created_at')->nullable();$t->unique(['source_id','cutoff']);});
        Schema::create('erp_migration_mappings',function(Blueprint $t){$t->bigIncrements('id');$t->string('source_id',128);$t->string('entity_type',32);$t->string('external_id',128);$t->string('target_type',64);$t->unsignedBigInteger('target_id');$t->char('row_digest',64);$t->unsignedBigInteger('batch_id');$t->unique(['source_id','entity_type','external_id'],'erp_migration_source_identity');});
        Schema::create('erp_migration_history',function(Blueprint $t){$t->bigIncrements('id');$t->unsignedBigInteger('batch_id');$t->unsignedBigInteger('unit_id')->index();$t->unsignedBigInteger('enrollment_id');$t->string('external_id',128);$t->dateTime('starts_at');$t->unsignedInteger('actual_minutes');$t->string('status',32);$t->string('financial_effect',16)->default('none');$t->timestamp('created_at')->nullable();$t->unique(['enrollment_id','starts_at']);});
    }
    public function down()
    {
        foreach(['erp_migration_history','erp_migration_mappings','erp_migration_batches','erp_followups','erp_task_items','erp_request_allocations','erp_payment_requests'] as $table)Schema::dropIfExists($table);
    }
}

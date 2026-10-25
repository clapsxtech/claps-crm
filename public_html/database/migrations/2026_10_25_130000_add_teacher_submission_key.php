<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('erp_marks',function(Blueprint $table){$table->uuid('submission_key')->nullable()->unique();$table->string('submission_hash',64)->nullable();}); }
 public function down(): void { Schema::table('erp_marks',function(Blueprint $table){$table->dropUnique(['submission_key']);$table->dropColumn(['submission_key','submission_hash']);}); }
};

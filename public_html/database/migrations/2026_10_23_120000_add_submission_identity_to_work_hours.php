<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
class AddSubmissionIdentityToWorkHours extends Migration
{
    public function up()
    {
        Schema::table('work_hours', function (Blueprint $table) {
            $table->uuid('submission_key')->nullable()->unique();
            $table->string('submission_hash', 64)->nullable();
        });
    }
    public function down()
    {
        // Removing these fields removes retry protection; export them before rollback.
        Schema::table('work_hours', function (Blueprint $table) {
            $table->dropUnique(['submission_key']);
            $table->dropColumn(['submission_key', 'submission_hash']);
        });
    }
}

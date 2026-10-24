<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIntervalsToErpExceptions extends Migration
{
    public function up()
    {
        Schema::table('erp_exceptions', function (Blueprint $t) {
            // Do not backfill: old rows mean an entire date in the participant's timezone.
            // Every new row snapshots all three fields, including full-day exceptions.
            $t->dateTime('starts_at')->nullable();
            $t->dateTime('ends_at')->nullable();
            $t->string('timezone')->nullable();
            $t->index(['unit_id', 'student_id', 'starts_at'], 'erp_exceptions_student_interval');
            $t->index(['unit_id', 'teacher_id', 'starts_at'], 'erp_exceptions_teacher_interval');
        });
    }

    public function down()
    {
        // Never silently widen a partial-day window to an old full-day block on rollback.
        if (\Illuminate\Support\Facades\DB::table('erp_exceptions')->where(function ($q) {
            $q->whereNotNull('starts_at')->orWhereNotNull('ends_at')->orWhereNotNull('timezone');
        })->exists()) {
            throw new \RuntimeException('Cannot remove exception intervals while snapshot rows exist. Preserve and explicitly resolve these records first.');
        }
        Schema::table('erp_exceptions', function (Blueprint $t) {
            $t->dropIndex('erp_exceptions_student_interval');
            $t->dropIndex('erp_exceptions_teacher_interval');
            $t->dropColumn(['starts_at', 'ends_at', 'timezone']);
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDueReportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('due_reports', function (Blueprint $table) {
            $table->id();
            $table->string('student_name')->nullable();
            $table->string('student_code')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->nullable();
            $table->string('hour_rate')->nullable();
            $table->string('monthly')->nullable();
            $table->string('advance_expected')->nullable();
            $table->string('section')->nullable();
            $table->string('admin_staff')->nullable();
            $table->string('last_month_balance')->nullable();
            $table->string('recived')->nullable();
            $table->string('total_advance')->nullable();
            $table->string('hour_taken')->nullable();
            $table->string('c_amount')->nullable();
            $table->string('error')->nullable();
            $table->string('balance')->nullable();
            $table->string('current_status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('due_reports');
    }
}

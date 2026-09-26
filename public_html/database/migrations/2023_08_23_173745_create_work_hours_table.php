<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkHoursTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('work_hours', function (Blueprint $table) {
            $table->id();
            $table->string('teacher_name')->nullable();
            $table->string('teacher_code')->nullable();
            $table->string('student_name')->nullable();
            $table->string('student_code')->nullable();
            $table->string('subject')->nullable();
            $table->string('date')->nullable();
            $table->string('date_submitted')->nullable();
            $table->string('chapter')->nullable();
            $table->string('topic')->nullable();
            $table->string('hour_taken')->nullable();
            $table->string('homework_status')->nullable();
            $table->string('dob')->nullable();
            $table->string('hour_payment')->nullable();
            $table->string('profit_per_hour')->nullable();
            $table->string('payment_per_hour')->nullable();
            $table->string('total_amount')->nullable();
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
        Schema::dropIfExists('work_hours');
    }
}

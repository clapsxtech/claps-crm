<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTeacherReportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('teacher_reports', function (Blueprint $table) {
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
        Schema::dropIfExists('teacher_reports');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTimeTablesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('time_tables', function (Blueprint $table) {
            $table->id();
            $table->string('student_name')->nullable();
            $table->string('section')->nullable();
            $table->string('class_syllabus')->nullable();

            $table->string('monday_time')->nullable();
            $table->string('monday_subject')->nullable();
            $table->string('monday_teacher')->nullable();
            $table->string('monday_hour')->nullable();
            $table->string('monday_number')->nullable();

            $table->string('tuesday_time')->nullable();
            $table->string('tuesday_subject')->nullable();
            $table->string('tuesday_teacher')->nullable();
            $table->string('tuesday_hour')->nullable();
            $table->string('tuesday_number')->nullable();

            $table->string('wednesday_time')->nullable();
            $table->string('wednesday_subject')->nullable();
            $table->string('wednesday_teacher')->nullable();
            $table->string('wednesday_hour')->nullable();
            $table->string('wednesday_number')->nullable();

            $table->string('thursday_time')->nullable();
            $table->string('thursday_subject')->nullable();
            $table->string('thursday_teacher')->nullable();
            $table->string('thursday_hour')->nullable();
            $table->string('thursday_number')->nullable();

            $table->string('friday_time')->nullable();
            $table->string('friday_subject')->nullable();
            $table->string('friday_teacher')->nullable();
            $table->string('friday_hour')->nullable();
            $table->string('friday_number')->nullable();

            $table->string('saturday_time')->nullable();
            $table->string('saturday_subject')->nullable();
            $table->string('saturday_teacher')->nullable();
            $table->string('saturday_hour')->nullable();
            $table->string('saturday_number')->nullable();

            $table->string('sunday_time')->nullable();
            $table->string('sunday_subject')->nullable();
            $table->string('sunday_teacher')->nullable();
            $table->string('sunday_hour')->nullable();
            $table->string('sunday_number')->nullable();
            $table->string('link')->nullable();
            $table->string('student_number')->nullable();
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
        Schema::dropIfExists('time_tables');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMarksListTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('marklists', function (Blueprint $table) {
            $table->id();
            $table->string('teacher_name')->nullable();
            $table->string('teacher_code')->nullable();
            $table->string('student_name')->nullable();
            $table->string('student_code')->nullable();
            $table->bigInteger('subject')->nullable();
            $table->string('chapter')->nullable();
            $table->string('mark_scored')->nullable();
            $table->string('total_mark')->nullable();
            $table->date('examination_date');
            $table->string('mode_of_exam')->nullable();
            $table->string('remark')->nullable();
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
        Schema::dropIfExists('marklists');
    }
}

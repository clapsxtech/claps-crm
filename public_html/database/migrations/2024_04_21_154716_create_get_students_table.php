<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGetStudentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('get_students', function (Blueprint $table) {
            $table->id();
            $table->string('team')->nullable();
            $table->string('student_name')->nullable();
            $table->string('status')->nullable();
            $table->string('student_code')->nullable();
            $table->string('hour_rate')->nullable();
            $table->string('section')->nullable();
            $table->string('number')->nullable();
            $table->string('total')->nullable();
            $table->string('last_month ')->nullable();
            $table->string('amount_recieved')->nullable();
            $table->string('staff_name')->nullable();
            $table->string('tt_hour')->nullable();
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
        Schema::dropIfExists('get_students');
    }
}

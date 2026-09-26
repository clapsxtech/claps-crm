<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMastersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('masters', function (Blueprint $table) {
            $table->id();
            $table->string('student_name')->nullable();
            $table->string('student_code')->nullable();
            $table->string('teacher_name')->nullable();
            $table->string('teacher_code')->nullable();
            $table->string('teacher_dob')->nullable()->format('d m y');
            $table->string('hour_payment')->nullable();
            $table->string('profit_per_hour')->nullable();
            $table->string('payment_per_hour')->nullable();
            $table->string('team')->nullable();
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
        Schema::dropIfExists('masters');
    }
}

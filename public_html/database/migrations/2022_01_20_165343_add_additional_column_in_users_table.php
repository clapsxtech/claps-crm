<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAdditionalColumnInUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone',125)->after('email');
            $table->date('dob')->nullable()->after('phone');
            $table->enum('gender',['MALE','FEMALE','OTHER'])->after('password');
            $table->string('profile_photo')->after('gender');
            $table->string('address')->after('profile_photo')->nullable();
            $table->longText('sticky_notes')->nullable()->after('address');
            $table->tinyInteger('status')->default(1)->after('remember_token');
            $table->dateTime('deleted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone');
            $table->dropColumn('dob');
            $table->dropColumn('gender');
            $table->dropColumn('profile_photo');
            $table->dropColumn('address');
            $table->dropColumn('sticky_notes');
            $table->dropColumn('status');
            $table->dropColumn('deleted_at');
        });
    }
}

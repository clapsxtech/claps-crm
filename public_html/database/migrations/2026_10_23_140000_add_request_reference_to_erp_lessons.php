<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
class AddRequestReferenceToErpLessons extends Migration
{
    public function up()
    {
        Schema::table('erp_lessons',function(Blueprint $t){$t->string('request_reference',120)->nullable()->unique();});
    }
    public function down()
    {
        Schema::table('erp_lessons',function(Blueprint $t){$t->dropUnique(['request_reference']);$t->dropColumn('request_reference');});
    }
}

<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
class IndexErpMigrationTargets extends Migration
{
    public function up(): void { Schema::table('erp_migration_mappings',function(Blueprint $table){$table->index(['target_type','target_id','entity_type'],'erp_migration_target_lookup');}); }
    public function down(): void { Schema::table('erp_migration_mappings',function(Blueprint $table){$table->dropIndex('erp_migration_target_lookup');}); }
}

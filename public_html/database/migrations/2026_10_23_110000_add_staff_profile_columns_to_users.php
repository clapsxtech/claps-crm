<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStaffProfileColumnsToUsers extends Migration
{
    public function up()
    {
        // Some installations added these columns manually. Preserve their existing schema and data.
        $strings = [
            'user_type' => 30, 'position' => 100, 'language' => 100,
            'commission_type' => 30, 'blood_group' => 5,
            'alternate_number' => 30, 'emergency_contact_number' => 30,
        ];
        foreach ($strings as $column => $length) {
            if (!Schema::hasColumn('users', $column)) {
                Schema::table('users', function (Blueprint $table) use ($column, $length) {
                    $table->string($column, $length)->nullable();
                });
            }
        }
        foreach (['custom_fields', 'bank_details'] as $column) {
            if (!Schema::hasColumn('users', $column)) {
                Schema::table('users', function (Blueprint $table) use ($column) {
                    $table->text($column)->nullable();
                });
            }
        }
        if (!Schema::hasColumn('users', 'is_commission_agent')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_commission_agent')->nullable();
            });
        }
        if (!Schema::hasColumn('users', 'service_expiry')) {
            Schema::table('users', function (Blueprint $table) {
                $table->date('service_expiry')->nullable();
            });
        }
    }

    public function down()
    {
        // Deliberately non-destructive: without a schema ownership ledger we cannot distinguish
        // columns created above from a pre-existing production extension. These nullable columns
        // are compatible with older application code. Remove only via a separate reviewed migration
        // after a backup, never as an automatic application rollback.
    }
}

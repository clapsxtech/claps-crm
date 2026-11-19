<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sales filters teachers by school level (LP, UP, HS) and by category (Standard, Advanced).
        Schema::table('erp_teachers', function (Blueprint $t) {
            $t->string('levels', 20)->nullable();
            $t->string('category', 20)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('erp_teachers', function (Blueprint $t) {
            $t->dropIndex(['category']);
            $t->dropColumn(['levels', 'category']);
        });
    }
};

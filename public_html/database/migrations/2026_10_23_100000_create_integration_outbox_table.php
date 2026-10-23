<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIntegrationOutboxTable extends Migration
{
    public function up()
    {
        Schema::create('integration_outbox', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('submission_key')->unique();
            $table->string('payload_hash', 64);
            $table->string('event_type', 64);
            $table->unsignedBigInteger('aggregate_id')->nullable();
            $table->json('payload');
            $table->string('status', 16)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->uuid('claim_token')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('last_error_code', 64)->nullable();
            $table->string('provider_receipt')->nullable();
            $table->timestamps();
            $table->index(['status', 'available_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('integration_outbox');
    }
}

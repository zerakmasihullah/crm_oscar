<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_client_mutations', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('resource', 100);
            $table->string('action', 20);
            $table->string('status', 32)->default('processed');
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('client_updated_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'client_uuid']);
            $table->index(['user_id', 'resource']);
            $table->index(['status', 'processed_at']);
        });

        Schema::create('sync_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('device_id')->unique();
            $table->string('name')->nullable();
            $table->string('user_agent')->nullable();
            $table->unsignedBigInteger('personal_access_token_id')->nullable()->index();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_devices');
        Schema::dropIfExists('sync_client_mutations');
    }
};

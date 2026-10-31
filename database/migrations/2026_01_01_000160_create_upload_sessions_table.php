<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upload_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename', 255);
            $table->string('safe_filename', 255);
            $table->string('extension', 32)->nullable();
            $table->string('declared_mime', 191)->nullable();
            $table->string('detected_mime', 191)->nullable();
            $table->unsignedBigInteger('total_bytes');
            $table->unsignedInteger('chunk_bytes');
            $table->unsignedInteger('total_chunks');
            $table->json('received_chunks')->nullable();
            $table->unsignedBigInteger('received_bytes')->default(0);
            $table->string('checksum_algo', 16)->default('sha256');
            $table->string('expected_checksum', 128)->nullable();
            $table->string('actual_checksum', 128)->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->string('destination', 32)->default('file_manager');
            $table->string('destination_root', 32)->nullable();
            $table->string('destination_path', 1000)->nullable();
            $table->string('final_path', 1000)->nullable();
            $table->string('chunk_disk', 64);
            $table->string('chunk_directory', 500);
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('assembled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upload_sessions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('type')->default('database');
            $table->string('trigger')->default('manual');
            $table->string('retention_tier')->default('manual');
            $table->string('disk');
            $table->string('path');
            $table->string('mirror_path')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('size')->nullable();
            $table->string('checksum')->nullable();
            $table->text('error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
            $table->index(['type', 'status']);
            $table->index(['trigger', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_records');
    }
};

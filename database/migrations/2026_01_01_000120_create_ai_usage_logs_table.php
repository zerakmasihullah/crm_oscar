<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('success')->index();
            $table->string('action', 40);
            $table->string('provider', 40)->nullable();
            $table->string('model_id', 64)->nullable();
            $table->string('model_name', 120)->nullable()->index();
            $table->string('instruction_preview', 280)->nullable();
            $table->string('input_preview', 280)->nullable();
            $table->string('output_preview', 280)->nullable();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->decimal('estimated_cost_usd', 12, 6)->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('logged_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index('logged_at');
            $table->index(['user_id', 'logged_at']);
            $table->index(['action', 'logged_at']);
            $table->index(['status', 'logged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};

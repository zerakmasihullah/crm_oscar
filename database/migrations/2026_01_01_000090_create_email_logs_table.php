<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('mailer', 64)->nullable()->index();
            $table->string('status', 32)->default('sent')->index();
            $table->string('subject')->nullable();
            $table->string('from_address')->nullable()->index();
            $table->string('from_name')->nullable();
            $table->json('to')->nullable();
            $table->json('cc')->nullable();
            $table->json('bcc')->nullable();
            $table->string('message_id')->nullable()->index();
            $table->string('related_type')->nullable()->index();
            $table->text('body_preview')->nullable();
            $table->text('error')->nullable();
            $table->json('headers')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('logged_at')->useCurrent()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'logged_at']);
            $table->index(['mailer', 'logged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authentication_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable()->index();
            $table->string('event', 64)->index();
            $table->boolean('success')->default(false)->index();
            $table->string('failure_reason')->nullable();
            $table->string('ip_address', 45)->nullable()->index();
            $table->json('ip_addresses')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_type', 32)->nullable();
            $table->string('browser')->nullable();
            $table->string('platform')->nullable();
            $table->string('session_id')->nullable()->index();
            $table->string('guard', 32)->default('web');
            $table->string('request_method', 16)->nullable();
            $table->string('request_path')->nullable();
            $table->text('referer')->nullable();
            $table->string('accept_language')->nullable();
            $table->string('country')->nullable();
            $table->string('country_code', 8)->nullable()->index();
            $table->string('region')->nullable();
            $table->string('city')->nullable()->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('timezone')->nullable();
            $table->string('isp')->nullable();
            $table->string('org')->nullable();
            $table->string('asn')->nullable();
            $table->boolean('is_private_ip')->default(false);
            $table->string('location_label')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('logged_at')->useCurrent()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'logged_at']);
            $table->index(['event', 'logged_at']);
            $table->index(['success', 'logged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authentication_logs');
    }
};

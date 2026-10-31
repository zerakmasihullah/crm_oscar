<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('newsletter_signups', function (Blueprint $table) {
            $table->boolean('unsubscribed')->default(false)->after('email');
            $table->timestamp('unsubscribed_at')->nullable()->after('unsubscribed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('newsletter_signups', function (Blueprint $table) {
            $table->dropColumn(['unsubscribed', 'unsubscribed_at']);
        });
    }
};

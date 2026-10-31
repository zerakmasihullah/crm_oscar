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
        Schema::table('promotion_recipients', function (Blueprint $table) {
            $table->integer('attempts')->default(0)->after('sent_at');
            $table->dateTime('last_attempt_at')->nullable()->after('attempts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promotion_recipients', function (Blueprint $table) {
            $table->dropColumn(['attempts', 'last_attempt_at']);
        });
    }
};

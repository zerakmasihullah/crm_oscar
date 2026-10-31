<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_settings', function (Blueprint $table) {
            $table->id();
            // Non-unique so soft-deleted keys can be reused.
            $table->string('key')->index();
            $table->text('value')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_settings');
    }
};

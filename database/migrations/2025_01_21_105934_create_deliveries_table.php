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
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->decimal('delivery_price', 8, 2)->nullable(); // Delivery price
            $table->decimal('free_delivery_limit', 8, 2)->nullable();
            $table->decimal('service_fees', 8, 2)->nullable();
            $table->decimal('discount_for_all', 8, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};

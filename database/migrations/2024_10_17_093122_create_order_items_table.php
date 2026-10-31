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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('menu_item_id');
            $table->integer('quantity')->default(1);
            $table->unsignedBigInteger('size_id')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->timestamps();
    
            // Foreign key constraints
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('menu_item_id')->references('id')->on('menu_items')->onDelete('cascade');
            $table->foreign('size_id')->references('id')->on('sizes')->onDelete('set null'); // Assumes there is a 'sizes' table
    
            // Indexes for faster queries
            $table->index(columns: 'order_id');
            $table->index('menu_item_id');
            $table->index('size_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};

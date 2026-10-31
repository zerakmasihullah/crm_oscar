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
        Schema::create('sizes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('menu_item_id');
            $table->string('size');
            $table->decimal('price', 10, 2);
            $table->timestamps();
    
            // Foreign key constraint
            $table->foreign('menu_item_id')->references('id')->on('menu_items')->onDelete('cascade'); // If the menu item is deleted, delete related sizes
    
            // Index for faster queries
            $table->index('menu_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sizes');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('toppings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('menu_item_id');
            $table->string('name');
            $table->integer('required')->default(0); //how many are requied
            $table->boolean('is_optional')->default(true);
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('menu_item_id')->references('id')->on('menu_items')->onDelete('cascade'); // If the menu item is deleted, delete related toppings

            // Index for faster queries
            $table->index('menu_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('toppings');
    }
};

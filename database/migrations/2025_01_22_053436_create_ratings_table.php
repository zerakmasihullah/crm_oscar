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
        Schema::create('ratings', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->unsignedBigInteger('menu_item_id');
            $table->unsignedBigInteger('user_id');
            $table->integer('score');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('menu_item_id')->references('id')->on('menu_items')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Ensure that a user can only rate a menu item once
            $table->unique(['menu_item_id', 'user_id']); // A user can only rate a menu item once
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};

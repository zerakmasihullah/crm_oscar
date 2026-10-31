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
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable(false); // Required
            $table->text('description')->nullable(); // Optional
            $table->string('image')->nullable(); // Optional
            $table->decimal('price', 8, 2)->nullable();
             $table->boolean('status')->default(true);
            $table->unsignedBigInteger('menu_id')->nullable(false);
            $table->timestamps();

            $table->foreign('menu_id')->references('id')->on('menus')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};

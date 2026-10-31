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
        Schema::create('menu_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('topping_id');
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->timestamps(); 

            // Foreign key constraint to link attachment to topping
            $table->foreign('topping_id')->references('id')->on('toppings')->onDelete('cascade'); 

            // Index for better performance on queries related to topping_id
            $table->index('topping_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_attachments');
    }
};

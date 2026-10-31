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
        Schema::create('order_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_item_id');
            $table->foreignId('topping_id');
            $table->unsignedBigInteger('attachment_id');
            $table->integer('quantity')->default(1);
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('order_item_id')->references('id')->on('order_items')->onDelete('cascade'); // If the order item is deleted, delete the related attachment
            $table->foreign('attachment_id')->references('id')->on('menu_attachments')->onDelete('cascade'); // If the attachment is deleted, delete the related order attachment

            // Indexes for performance optimization
            $table->index('order_item_id');
            $table->index('attachment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_attachments');
    }
};

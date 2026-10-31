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
        Schema::create('order_notifications', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->unsignedBigInteger('customer_id')->nullable(); // Nullable foreign key to the users table (customer)
            $table->unsignedBigInteger('order_id'); // Foreign key to the orders table
            $table->boolean('read_status')->default(false); // Boolean indicating if the notification has been read
            $table->timestamps(); // Timestamps for created_at and updated_at

            // Foreign key constraints
            $table->foreign('customer_id')->references('id')->on('users')->onDelete('set null'); // If the customer is deleted, set the customer_id to null
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade'); // If the order is deleted, delete the notification

            // Indexes for performance optimization
            $table->index('customer_id');
            $table->index('order_id');
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_notifications');
    }
};

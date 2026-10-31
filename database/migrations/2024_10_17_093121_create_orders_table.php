<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->text('address')->nullable();
            // This will store status as integers: 1 = pending, 2 = accepted, 3 = rejected, 4 = delivering, 5 = completed
            $table->integer('status')->default(1);
            // this will store type as 1 = delivery 2 = collection
            $table->integer('type')->default(1);
            $table->decimal('total_price', 10, 2)->nullable();
            $table->string('payment_method')->default('cash');
            $table->text('description')->nullable();
            $table->string('phone_number')->nullable();
            $table->unsignedBigInteger('deliveryman_id')->nullable();
            $table->string('area_code')->nullable();
            $table->string('longitude')->nullable();
            $table->string('latitude')->nullable();
            $table->dateTime('delivery_datetime')->nullable();
            $table->string('payment_id')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('customer_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('deliveryman_id')->references('id')->on('users')->onDelete('set null'); // Deliveryman can be null

            // Indexes for fast lookups
            $table->index('customer_id');
            $table->index('deliveryman_id');
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

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
        Schema::create('chat', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sender'); // ID of the sender
            $table->unsignedBigInteger('receiver'); // ID of the receiver
            $table->text('message')->nullable(); // Chat message (nullable)
            $table->string('attachment')->nullable(); // File path or URL for attachments (nullable)
            $table->boolean('is_sent')->default(false); // Whether the message is sent
            $table->boolean('is_delivered')->default(false); // Whether the message is delivered
            $table->boolean('is_read')->default(false); // Whether the message is read
            $table->timestamps(); // Created at and updated at timestamps

            // Foreign key constraints
            $table->foreign('sender')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('receiver')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat');
    }
};

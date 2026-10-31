<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_trash_entries', function (Blueprint $table) {
            $table->id();
            $table->string('root', 32)->index();
            $table->string('original_path', 1000);
            $table->string('trash_path', 1000);
            $table->string('name');
            $table->boolean('is_directory')->default(false);
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_trash_entries');
    }
};

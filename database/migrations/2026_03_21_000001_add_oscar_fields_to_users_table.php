<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_number')->nullable()->after('email');
            $table->string('image')->nullable()->after('phone_number');
            $table->integer('role')->default(2)->after('image'); // 1 = delivery, 2 = customer, 3 = admin
            $table->string('area_code')->nullable()->after('role');
            $table->string('longitude')->nullable()->after('area_code');
            $table->string('latitude')->nullable()->after('longitude');
            $table->boolean('is_staff')->default(false)->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone_number',
                'image',
                'role',
                'area_code',
                'longitude',
                'latitude',
                'is_staff',
            ]);
        });
    }
};

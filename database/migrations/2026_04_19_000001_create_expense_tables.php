<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('expense_date');
            $table->string('vendor')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['expense_date', 'expense_category_id']);
        });

        $now = now();
        $categories = [
            ['name' => 'Rent', 'slug' => 'rent', 'sort_order' => 10],
            ['name' => 'Utilities', 'slug' => 'utilities', 'sort_order' => 20],
            ['name' => 'Salaries & wages', 'slug' => 'salaries', 'sort_order' => 30],
            ['name' => 'Ingredients & food', 'slug' => 'ingredients', 'sort_order' => 40],
            ['name' => 'Packaging', 'slug' => 'packaging', 'sort_order' => 50],
            ['name' => 'Marketing', 'slug' => 'marketing', 'sort_order' => 60],
            ['name' => 'Maintenance', 'slug' => 'maintenance', 'sort_order' => 70],
            ['name' => 'Transport & delivery', 'slug' => 'transport', 'sort_order' => 80],
            ['name' => 'Software & subscriptions', 'slug' => 'software', 'sort_order' => 90],
            ['name' => 'Insurance', 'slug' => 'insurance', 'sort_order' => 100],
            ['name' => 'Taxes & fees', 'slug' => 'taxes', 'sort_order' => 110],
            ['name' => 'Other', 'slug' => 'other', 'sort_order' => 120],
        ];

        DB::table('expense_categories')->insert(
            collect($categories)->map(fn (array $row) => [
                ...$row,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};

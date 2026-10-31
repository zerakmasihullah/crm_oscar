<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class KitDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! User::query()->where('email', 'staff@example.com')->exists()) {
            $staff = User::query()->create([
                'name' => 'Demo Staff',
                'email' => 'staff@example.com',
                'password' => 'password',
                'email_verified_at' => now(),
                'is_active' => true,
            ]);

            $staff->syncRoles(['User']);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Services\MisPermissionRegistrar;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use App\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(MisPermissionRegistrar::class)->sync();

        $all = Permission::all();

        $owner = Role::findOrCreate('Owner');
        $owner->syncPermissions($all);

        $admin = Role::findOrCreate('Admin');
        $admin->syncPermissions($all);

        $user = Role::findOrCreate('User');
        $user->syncPermissions([
            'dashboard.view',
        ]);
    }
}

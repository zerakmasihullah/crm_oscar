<?php

namespace Database\Seeders;

use App\Models\OrgSetting;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => 'password',
                'email_verified_at' => now(),
                'is_active' => true,
                'role' => User::ROLE_ADMIN,
                'is_staff' => true,
            ],
        );

        $admin->syncRoles(['Owner']);

        OrgSetting::setValue('app_name', config('app.name', 'Oscar'));
        OrgSetting::setValue('default_locale', config('app.locale', 'en'));
        OrgSetting::setValue('calendar_system', 'gregorian');
        OrgSetting::setValue('logo_path', '/brand/oscar-mark.svg');
        OrgSetting::setValue(
            \App\Support\ThemeColors::SETTING_KEY,
            json_encode(\App\Support\ThemeColors::defaults()),
        );

        $this->call(KitDemoSeeder::class);

        app(NotificationService::class)->notifyUser(
            $admin,
            'Welcome to Oscar CMS',
            'You are signed in as Owner. Manage orders, menu, delivery, and staff from the sidebar.',
            'success',
            '/settings/general',
        );
    }
}

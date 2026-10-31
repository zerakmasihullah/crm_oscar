<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Support\ThemeColors;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use App\Models\Role;
use Tests\TestCase;

class AppearanceSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('settings.manage');
        $role = Role::findOrCreate('Owner');
        $role->givePermissionTo('settings.manage');
    }

    public function test_appearance_page_includes_theme_colors(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Owner');

        $this->actingAs($user)
            ->get(route('appearance.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/Appearance')
                ->has('themeColors.brand')
                ->has('themeColors.secondary')
                ->has('themeColors.accent')
                ->has('themeColors.radius')
                ->has('themeColors.density')
                ->has('themeColors.sidebarActive')
                ->has('themeColors.shell')
                ->has('branding.app_name')
                ->has('branding.calendar_system')
                ->where('canManageTheme', true)
            );
    }

    public function test_general_settings_redirects_to_appearance(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Owner');

        $this->actingAs($user)
            ->get(route('settings.general.edit'))
            ->assertRedirect(route('appearance.edit'));
    }

    public function test_glass_style_is_independent_from_color_mode(): void
    {
        $this->withUnencryptedCookies([
            'appearance' => 'light',
            'interface_style' => 'glass',
        ])
            ->get(route('login'))
            ->assertOk()
            ->assertSee('class="glass"', false)
            ->assertDontSee('class="dark glass"', false);
    }

    public function test_admin_can_update_and_reset_theme_colors(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Owner');

        $payload = [
            'brand' => '#112233',
            'secondary' => '#aabbcc',
            'accent' => '#00aa88',
            'accentDark' => '#33ddbb',
            'backgroundLight' => '#f5f5f5',
            'backgroundDark' => '#111111',
            'radius' => 'soft',
            'density' => 'compact',
            'sidebar_active' => 'accent',
            'shell' => 'header',
        ];

        $this->actingAs($user)
            ->put(route('appearance.colors.update'), $payload)
            ->assertRedirect();

        $current = ThemeColors::current();
        $this->assertSame('#112233', $current['brand']);
        $this->assertSame('soft', $current['radius']);
        $this->assertSame('compact', $current['density']);
        $this->assertSame('accent', $current['sidebar_active']);
        $this->assertSame('header', $current['shell']);
        $this->assertTrue(ThemeColors::isCustomized());

        $this->actingAs($user)
            ->delete(route('appearance.colors.reset'))
            ->assertRedirect();

        $this->assertFalse(ThemeColors::isCustomized());
        $this->assertSame(ThemeColors::defaults()['brand'], ThemeColors::current()['brand']);
        $this->assertSame('pill', ThemeColors::current()['sidebar_active']);
        $this->assertSame('sidebar-floating', ThemeColors::current()['shell']);
    }

    public function test_user_without_permission_cannot_update_theme_colors(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('appearance.colors.update'), ThemeColors::defaults())
            ->assertForbidden();
    }
}

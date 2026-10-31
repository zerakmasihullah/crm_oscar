<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\AuthorizesMisPermissions;
use App\Http\Controllers\Controller;
use App\Models\OrgSetting;
use App\Services\LocaleRegistry;
use App\Support\ThemeColors;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AppearanceSettingsController extends Controller
{
    use AuthorizesMisPermissions;

    public function edit(Request $request, LocaleRegistry $locales): Response
    {
        $theme = ThemeColors::current();
        $canManage = $request->user()?->can('settings.manage') ?? false;

        return Inertia::render('settings/Appearance', [
            'themeColors' => [
                ...Arr::except($theme, ['sidebar_active']),
                'sidebarActive' => $theme['sidebar_active'],
            ],
            'themeColorsCustomized' => ThemeColors::isCustomized(),
            'canManageTheme' => $canManage,
            'branding' => OrgSetting::general(),
            'locales' => collect($locales->all())
                ->map(fn (array $config, string $code): array => [
                    'code' => $code,
                    'name' => $config['name'],
                    'native' => $config['native'],
                ])
                ->values()
                ->all(),
        ]);
    }

    public function updateColors(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'settings.manage');

        $validated = $request->validate([
            'brand' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accentDark' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'backgroundLight' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'backgroundDark' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'radius' => ['required', 'string', Rule::in(['soft', 'medium', 'sharp'])],
            'density' => ['required', 'string', Rule::in(['comfortable', 'compact'])],
            'sidebar_active' => ['required', 'string', Rule::in(['pill', 'accent', 'soft'])],
            'shell' => ['required', 'string', Rule::in(['sidebar-floating', 'sidebar-inset', 'sidebar-flush', 'header'])],
        ]);

        ThemeColors::store($validated);

        return back()->with('success', __('Theme colors saved.'));
    }

    public function resetColors(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'settings.manage');

        ThemeColors::reset();

        return back()->with('success', __('Theme colors reset.'));
    }
}

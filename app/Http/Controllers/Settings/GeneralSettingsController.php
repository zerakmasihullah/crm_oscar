<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\AuthorizesMisPermissions;
use App\Http\Controllers\Controller;
use App\Models\OrgSetting;
use App\Services\LocaleRegistry;
use App\Support\CalendarSystem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GeneralSettingsController extends Controller
{
    use AuthorizesMisPermissions;

    public function edit(): RedirectResponse
    {
        return redirect()->route('appearance.edit');
    }

    public function update(Request $request, LocaleRegistry $locales): RedirectResponse
    {
        $this->authorizePermission($request, 'settings.manage');

        $validated = $request->validate([
            'app_name' => ['required', 'string', 'max:255'],
            'default_locale' => ['required', 'string', Rule::in($locales->codes())],
            'calendar_system' => ['sometimes', 'string', Rule::in(CalendarSystem::options())],
            'logo' => ['nullable', 'image', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
            'favicon' => ['nullable', 'image', 'max:1024'],
            'remove_favicon' => ['sometimes', 'boolean'],
        ]);

        OrgSetting::setValue('app_name', $validated['app_name']);
        OrgSetting::setValue('default_locale', $validated['default_locale']);

        if (array_key_exists('calendar_system', $validated)) {
            OrgSetting::setValue('calendar_system', $validated['calendar_system']);
        }

        if ($request->boolean('remove_logo')) {
            OrgSetting::trashBrandingPath('logo_path');
        }

        if ($request->hasFile('logo')) {
            OrgSetting::trashBrandingPath('logo_path');
            $path = $request->file('logo')->store('branding', 'public');
            OrgSetting::setValue('logo_path', $path);
        }

        if ($request->boolean('remove_favicon')) {
            OrgSetting::trashBrandingPath('favicon_path');
        }

        if ($request->hasFile('favicon')) {
            OrgSetting::trashBrandingPath('favicon_path');
            $path = $request->file('favicon')->store('branding', 'public');
            OrgSetting::setValue('favicon_path', $path);
        }

        config(['app.name' => $validated['app_name']]);

        return back()->with('success', __('Settings saved.'));
    }
}

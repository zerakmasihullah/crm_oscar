<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\AuthorizesMisPermissions;
use App\Http\Controllers\Controller;
use App\Support\SystemControl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SystemControlSettingsController extends Controller
{
    use AuthorizesMisPermissions;

    public function edit(Request $request): Response
    {
        $this->authorizePermission($request, 'settings.manage');

        $payload = SystemControl::forAdmin();

        return Inertia::render('settings/SystemControl', [
            'settings' => $payload['settings'],
            'modules' => $payload['modules'],
            'definitions' => SystemControl::definitions(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'settings.manage');

        $rules = [];

        foreach (array_keys(SystemControl::KEYS) as $key) {
            $rules[$key] = ['required', 'boolean'];
        }

        $validated = $request->validate($rules);

        SystemControl::save($validated);

        return back()->with('success', __('System control settings saved.'));
    }
}

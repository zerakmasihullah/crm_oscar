<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\AuthorizesMisPermissions;
use App\Http\Controllers\Controller;
use App\Support\GoogleAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GoogleAuthSettingsController extends Controller
{
    use AuthorizesMisPermissions;

    public function edit(Request $request): Response
    {
        $this->authorizePermission($request, 'settings.manage');

        return Inertia::render('settings/GoogleAuth', [
            'settings' => GoogleAuth::forAdmin(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'settings.manage');

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'client_id' => ['nullable', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:255'],
            'redirect_uri' => ['nullable', 'string', 'max:500', 'url'],
        ]);

        GoogleAuth::save($validated);

        return back()->with('success', __('Google Auth settings saved.'));
    }
}

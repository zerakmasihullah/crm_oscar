<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\AuthorizesMisPermissions;
use App\Http\Controllers\Controller;
use App\Models\SyncDevice;
use App\Support\OfflineSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OfflineSettingsController extends Controller
{
    use AuthorizesMisPermissions;

    public function edit(Request $request): Response
    {
        $this->authorizePermission($request, 'settings.manage');

        return Inertia::render('settings/Offline', [
            'settings' => OfflineSettings::forAdmin(),
            'stats' => OfflineSettings::stats(),
            'devices' => SyncDevice::query()
                ->with('user:id,name,email')
                ->latest('last_seen_at')
                ->limit(50)
                ->get()
                ->map(fn (SyncDevice $device): array => [
                    'id' => $device->id,
                    'device_id' => $device->device_id,
                    'name' => $device->name,
                    'user' => $device->user?->only(['id', 'name', 'email']),
                    'user_agent' => $device->user_agent,
                    'last_seen_at' => $device->last_seen_at?->toIso8601String(),
                    'revoked_at' => $device->revoked_at?->toIso8601String(),
                ]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'settings.manage');

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'pwa_enabled' => ['required', 'boolean'],
            'auto_sync' => ['required', 'boolean'],
            'warm_cache' => ['required', 'boolean'],
            'attachments_enabled' => ['required', 'boolean'],
            'metrics_enabled' => ['required', 'boolean'],
            'max_batch_size' => ['required', 'integer', 'min:1', 'max:200'],
            'max_outbox_size' => ['required', 'integer', 'min:10', 'max:5000'],
            'attachment_max_bytes' => ['required', 'integer', 'min:1024', 'max:20971520'],
            'slow_request_ms' => ['required', 'integer', 'min:100', 'max:60000'],
        ]);

        OfflineSettings::save($validated);

        return back()->with('success', __('Offline settings saved.'));
    }

    public function revokeDevice(Request $request, SyncDevice $device): RedirectResponse
    {
        $this->authorizePermission($request, 'settings.manage');

        $device->revoke();

        return back()->with('success', __('Device revoked.'));
    }
}

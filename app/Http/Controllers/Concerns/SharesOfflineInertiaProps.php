<?php

namespace App\Http\Controllers\Concerns;

use App\Support\OfflineSettings;

trait SharesOfflineInertiaProps
{
    /**
     * @return array{offlineEnabled: bool, offlineResource?: string}
     */
    protected function offlineInertiaProps(?string $resource = null): array
    {
        $props = [
            'offlineEnabled' => OfflineSettings::enabled(),
        ];

        if ($resource !== null) {
            $props['offlineResource'] = $resource;
        }

        return $props;
    }

    protected function offlineEnabled(): bool
    {
        return OfflineSettings::enabled();
    }
}

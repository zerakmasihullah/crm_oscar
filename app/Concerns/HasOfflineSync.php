<?php

namespace App\Concerns;

/**
 * Marker + helpers for models that participate in offline sync.
 */
trait HasOfflineSync
{
    public function initializeHasOfflineSync(): void
    {
        $this->mergeFillable([
            'client_uuid',
            'client_updated_at',
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function offlineSyncCasts(): array
    {
        return [
            'client_updated_at' => 'datetime',
        ];
    }
}

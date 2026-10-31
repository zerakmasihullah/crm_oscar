<?php

namespace App\Concerns;

use App\Models\StatusChangeLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasStatusChangeLogs
{
    public function statusChangeLogs(): MorphMany
    {
        return $this->morphMany(StatusChangeLog::class, 'subject')->latest();
    }

    public function logStatusChange(string $toStatus, ?string $fromStatus = null, ?User $by = null): ?StatusChangeLog
    {
        if ($fromStatus !== null && $fromStatus === $toStatus) {
            return null;
        }

        return $this->statusChangeLogs()->create([
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by' => $by?->id ?? auth()->id(),
        ]);
    }
}

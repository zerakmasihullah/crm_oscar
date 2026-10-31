<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserSessionManager
{
    /**
     * @return Collection<int, object{id: string, ip_address: ?string, user_agent: ?string, last_activity: int, is_current: bool}>
     */
    public function listForUser(User $user, ?string $currentSessionId): Collection
    {
        if (config('session.driver') !== 'database') {
            return collect();
        }

        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($row) => (object) [
                'id' => $row->id,
                'ip_address' => $row->ip_address,
                'user_agent' => is_string($row->user_agent) ? mb_substr($row->user_agent, 0, 120) : null,
                'last_activity' => (int) $row->last_activity,
                'is_current' => $currentSessionId !== null && $row->id === $currentSessionId,
            ]);
    }

    public function revokeOthers(User $user, ?string $currentSessionId): int
    {
        $deleted = 0;

        if (config('session.driver') === 'database') {
            $query = DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id);

            if ($currentSessionId !== null) {
                $query->where('id', '!=', $currentSessionId);
            }

            $deleted = $query->delete();
        }

        $user->forceFill([
            'session_version' => ((int) $user->session_version) + 1,
        ])->save();

        session()->put('auth.session_version', (int) $user->session_version);

        return $deleted;
    }

    public function stampSession(User $user): void
    {
        session()->put('auth.session_version', (int) $user->session_version);
    }
}

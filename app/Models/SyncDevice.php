<?php

namespace App\Models;

use App\Concerns\Trashable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\PersonalAccessToken;

class SyncDevice extends Model
{
    use SoftDeletes;
    use Trashable;

    protected $fillable = [
        'user_id',
        'device_id',
        'name',
        'user_agent',
        'personal_access_token_id',
        'last_seen_at',
        'revoked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function personalAccessToken(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'personal_access_token_id');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function revoke(): void
    {
        if ($this->personal_access_token_id) {
            PersonalAccessToken::query()
                ->whereKey($this->personal_access_token_id)
                ->delete();
        }

        $this->forceFill([
            'revoked_at' => now(),
            'personal_access_token_id' => null,
        ])->save();
    }

    public function touchSeen(): void
    {
        $this->forceFill(['last_seen_at' => now()])->save();
    }
}

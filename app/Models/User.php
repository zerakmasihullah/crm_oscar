<?php

namespace App\Models;

use App\Concerns\HasStatusChangeLogs;
use Database\Factories\UserFactory;
use App\Concerns\Trashable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Ai\Concerns\HasConversations;
use Laravel\Cashier\Billable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $google_id
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $is_active
 * @property Carbon|null $disabled_at
 * @property int|null $disabled_by
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property int $session_version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'email',
    'google_id',
    'password',
    'is_active',
    'disabled_at',
    'disabled_by',
    'session_version',
    'phone_number',
    'image',
    'role',
    'area_code',
    'address',
    'longitude',
    'latitude',
    'is_staff',
    'fcm_token',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    public const ROLE_DELIVERY_MAN = 1;

    public const ROLE_CUSTOMER = 2;

    public const ROLE_ADMIN = 3;

    /** @use HasFactory<UserFactory> */
    use Billable, HasApiTokens, HasConversations, HasFactory, HasRoles, HasStatusChangeLogs, LogsActivity, Notifiable, PasskeyAuthenticatable, SoftDeletes, Trashable, TwoFactorAuthenticatable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_staff' => 'boolean',
            'disabled_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'session_version' => 'integer',
            'role' => 'integer',
        ];
    }

    public function conversations(): MorphMany
    {
        return $this->morphMany(AiConversation::class, 'participant');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function disabledBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'disabled_by');
    }

    public function disable(?User $by = null): void
    {
        $from = $this->is_active ? 'active' : 'disabled';

        $this->update([
            'is_active' => false,
            'disabled_at' => now(),
            'disabled_by' => $by?->id ?? auth()->id(),
        ]);

        $this->logStatusChange('disabled', $from, $by);
    }

    public function enable(?User $by = null): void
    {
        $from = $this->is_active ? 'active' : 'disabled';

        $this->update([
            'is_active' => true,
            'disabled_at' => null,
            'disabled_by' => null,
        ]);

        $this->logStatusChange('active', $from, $by);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    public function delivery_man(): HasMany
    {
        return $this->hasMany(Order::class, 'deliveryman_id');
    }

    public function orderNotifications(): HasMany
    {
        return $this->hasMany(OrderNotification::class, 'customer_id');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class, 'user_id');
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Chat::class, 'sender');
    }

    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Chat::class, 'receiver');
    }

    public function hasOscarRole(int $role): bool
    {
        return (int) $this->role === $role;
    }

    public function getOscarRoleName(): string
    {
        return match ((int) $this->role) {
            self::ROLE_DELIVERY_MAN => 'Delivery Man',
            self::ROLE_CUSTOMER => 'Customer',
            self::ROLE_ADMIN => 'Admin',
            default => 'Unknown',
        };
    }

    /**
     * CMS / admin panel access (not the customer storefront).
     * Customers order via Sanctum on the website and must not use /login or /dashboard.
     */
    public function canAccessAdminPanel(): bool
    {
        if ($this->hasAnyRole(['Owner', 'Admin', 'User'])) {
            return true;
        }

        if ($this->is_staff || (int) $this->role === self::ROLE_ADMIN) {
            return true;
        }

        return false;
    }

    public function isCustomerOnly(): bool
    {
        return ! $this->canAccessAdminPanel();
    }
}

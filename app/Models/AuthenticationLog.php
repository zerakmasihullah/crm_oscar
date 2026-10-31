<?php

namespace App\Models;

use App\Concerns\Trashable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AuthenticationLog extends Model
{
    use SoftDeletes;
    use Trashable;
    public const EVENT_LOGIN_SUCCESS = 'login_success';

    public const EVENT_LOGIN_FAILED = 'login_failed';

    public const EVENT_LOGOUT = 'logout';

    public const EVENT_TWO_FACTOR_CHALLENGED = 'two_factor_challenged';

    public const EVENT_TWO_FACTOR_FAILED = 'two_factor_failed';

    public const EVENT_LOCKOUT = 'lockout';

    public const EVENT_SESSION_REVOKED = 'session_revoked';

    protected $fillable = [
        'user_id',
        'email',
        'event',
        'success',
        'failure_reason',
        'ip_address',
        'ip_addresses',
        'user_agent',
        'device_type',
        'browser',
        'platform',
        'session_id',
        'guard',
        'request_method',
        'request_path',
        'referer',
        'accept_language',
        'country',
        'country_code',
        'region',
        'city',
        'latitude',
        'longitude',
        'timezone',
        'isp',
        'org',
        'asn',
        'is_private_ip',
        'location_label',
        'metadata',
        'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'ip_addresses' => 'array',
            'latitude' => 'float',
            'longitude' => 'float',
            'is_private_ip' => 'boolean',
            'metadata' => 'array',
            'logged_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

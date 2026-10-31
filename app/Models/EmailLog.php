<?php

namespace App\Models;

use App\Concerns\Trashable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailLog extends Model
{
    use SoftDeletes;
    use Trashable;
    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'mailer',
        'status',
        'subject',
        'from_address',
        'from_name',
        'to',
        'cc',
        'bcc',
        'message_id',
        'related_type',
        'body_preview',
        'error',
        'headers',
        'metadata',
        'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'to' => 'array',
            'cc' => 'array',
            'bcc' => 'array',
            'headers' => 'array',
            'metadata' => 'array',
            'logged_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

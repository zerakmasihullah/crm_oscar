<?php

namespace App\Models;

use App\Concerns\HasAttachments;
use App\Concerns\Trashable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FieldReport extends Model
{
    use HasAttachments;
    use SoftDeletes;
    use Trashable;

    protected $fillable = [
        'client_uuid',
        'user_id',
        'title',
        'body',
        'location',
        'status',
        'attachments_meta',
        'client_updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attachments_meta' => 'array',
            'client_updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

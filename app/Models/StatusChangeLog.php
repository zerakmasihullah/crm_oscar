<?php

namespace App\Models;

use App\Concerns\Trashable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StatusChangeLog extends Model
{
    use SoftDeletes;
    use Trashable;
    protected $fillable = [
        'subject_type',
        'subject_id',
        'from_status',
        'to_status',
        'changed_by',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

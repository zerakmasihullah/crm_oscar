<?php

namespace App\Models;

use App\Concerns\HasOfflineSync;
use App\Concerns\Trashable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FieldNote extends Model
{
    use HasOfflineSync;
    use SoftDeletes;
    use Trashable;

    protected $fillable = [
        'client_uuid',
        'name',
        'client_updated_at',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'client_updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

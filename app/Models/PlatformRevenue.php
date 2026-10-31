<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformRevenue extends Model
{
    use HasFactory;

    protected $fillable = [
        'platform_id',
        'amount',
        'revenue_date',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'revenue_date' => 'date',
    ];

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }
}

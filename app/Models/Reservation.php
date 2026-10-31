<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'person',
        'reservation_date',
        'reservation_time',
        'message',
        'accepted',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'reservation_date' => 'date',
            'accepted' => 'boolean',
        ];
    }
}

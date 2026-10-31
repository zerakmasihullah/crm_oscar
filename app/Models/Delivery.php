<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    protected $fillable = [
        'delivery_price',
        'free_delivery_limit',
        'service_fees',
        'discount_for_all',
        'min_order',
    ];

    protected function casts(): array
    {
        return [
            'delivery_price' => 'decimal:2',
            'free_delivery_limit' => 'decimal:2',
            'service_fees' => 'decimal:2',
            'discount_for_all' => 'decimal:2',
            'min_order' => 'decimal:2',
        ];
    }
}

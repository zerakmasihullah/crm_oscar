<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'image',
        'price',
        'status',
        'menu_id',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'status' => 'boolean',
        ];
    }

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }

    public function sizes()
    {
        return $this->hasMany(Size::class);
    }


    public function order_items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function toppings()
    {
        return $this->hasMany(Topping::class);
    }

    public function ratings()
    {
        return $this->hasMany(Rating::class);
    }
}

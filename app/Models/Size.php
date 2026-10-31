<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Size extends Model
{
    protected $fillable = [
        'menu_item_id',
        'size',
        'price',
    ];


    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

}

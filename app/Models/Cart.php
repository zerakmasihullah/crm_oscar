<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function size()
    {
        return $this->belongsTo(Size::class);
    }

    public function attachments()
    {
        return $this->hasMany(CartAttachment::class, 'cart_id');
    }
}

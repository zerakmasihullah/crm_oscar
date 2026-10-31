<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartAttachment extends Model
{
    public function topping()
    {
        return $this->belongsTo(Topping::class, 'topping_id');
    }

    public function attachment()
    {
        return $this->belongsTo(MenuAttachment::class, 'attachment_id');
    }

    public function cart()
    {
        return $this->belongsTo(Cart::class, 'cart_id');
    }
}

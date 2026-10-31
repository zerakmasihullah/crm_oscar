<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderAttachment extends Model
{
    protected $fillable = [
        'order_item_id',
        'topping_id',
        'attachment_id',
        'quantity',
    ];

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function attachment()
    {
        return $this->belongsTo(MenuAttachment::class);
    }

    public function topping()
    {
        return $this->belongsTo(Topping::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuAttachment extends Model
{
    protected $table = 'menu_attachments';

    protected $fillable = [
        'topping_id',
        'name',
        'price',
    ];

    public function topping()
    {
        return $this->belongsTo(Topping::class);
    }

    public function orderAttachments()
    {
        return $this->hasMany(OrderAttachment::class, 'attachment_id');
    }
}
